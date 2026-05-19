<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/image.php';

auth_require();

$db = db();

function service_section_definitions(): array
{
    return [
        'alquileres' => [
            'label' => 'Alquileres',
            'url' => '/alquileres',
            'cover_path' => 'assets/site/alquileres.png',
            'intro' => 'Muebles y objetos para producciones, eventos, vidrieras y ambientaciones.',
            'content_html' => '<h2>Alquileres para producciones y eventos</h2><p>Contamos con muebles, objetos decorativos, luminarias y piezas especiales para ambientaciones, filmaciones, vidrieras, sesiones fotograficas y eventos.</p><p>Podemos asesorarte para elegir piezas con caracter, coordinar disponibilidad y preparar cada objeto para su retiro o traslado.</p>',
        ],
        'compra-venta' => [
            'label' => 'Compra y venta',
            'url' => '/compra-venta',
            'cover_path' => 'assets/site/venta.png',
            'intro' => 'Tasacion sin cargo y piezas seleccionadas para sumar caracter a cada ambiente.',
            'content_html' => '<h2>Compra y venta de antiguedades</h2><p>Seleccionamos muebles, objetos y piezas con historia para quienes buscan incorporar caracter, oficio y materiales nobles a sus espacios.</p><p>Tambien recibimos consultas por tasaciones y compra de piezas. Evaluamos cada objeto con criterio y acompanamos el proceso de forma clara.</p>',
        ],
        'restauraciones' => [
            'label' => 'Restauraciones',
            'url' => '/restauraciones',
            'cover_path' => 'assets/site/restauraciones.png',
            'intro' => 'Lustre, retapizados, esterillados y trabajos artesanales con criterio de epoca.',
            'content_html' => '<h2>Restauraciones a medida</h2><p>Trabajamos en la recuperacion de muebles y objetos respetando su identidad, materiales y epoca. Realizamos lustres, retapizados, esterillados y reparaciones artesanales.</p><p>Cada trabajo se evalua segun el estado de la pieza y el resultado buscado, con una mirada puesta en conservar su valor y funcionalidad.</p>',
        ],
    ];
}

function service_section_prefix(string $slug): string
{
    return 'service_' . str_replace('-', '_', $slug) . '_';
}

function service_section_setting(PDO $db, string $key, string $value): void
{
    $stmt = $db->prepare("
        INSERT INTO site_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, datetime('now'))
        ON CONFLICT(setting_key) DO UPDATE SET
            setting_value = excluded.setting_value,
            updated_at = datetime('now')
    ");
    $stmt->execute([$key, $value]);
}

function service_section_load(PDO $db, string $slug): array
{
    $definitions = service_section_definitions();
    $defaults = $definitions[$slug];
    $prefix = service_section_prefix($slug);
    $keys = [
        $prefix . 'title',
        $prefix . 'intro',
        $prefix . 'cover_path',
        $prefix . 'content_html',
    ];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'slug' => $slug,
        'url' => $defaults['url'],
        'title' => trim((string)($rows[$prefix . 'title'] ?? $defaults['label'])),
        'intro' => trim((string)($rows[$prefix . 'intro'] ?? $defaults['intro'])),
        'cover_path' => trim((string)($rows[$prefix . 'cover_path'] ?? $defaults['cover_path'])),
        'content_html' => trim((string)($rows[$prefix . 'content_html'] ?? $defaults['content_html'])) ?: $defaults['content_html'],
    ];
}

function service_section_sanitize_html(string $html): string
{
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><img><figure><figcaption><blockquote>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/\s+class\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/(href|src)\s*=\s*([\'"])\s*javascript:.*?\2/i', '$1="#"', $html) ?? $html;
    $html = preg_replace_callback('/<a\b([^>]*)>/i', static function (array $matches): string {
        $attrs = $matches[1];
        if (!preg_match('/\btarget\s*=/i', $attrs)) {
            $attrs .= ' target="_blank"';
        }
        if (!preg_match('/\brel\s*=/i', $attrs)) {
            $attrs .= ' rel="noopener"';
        }
        return '<a' . $attrs . '>';
    }, $html) ?? $html;

    return trim($html);
}

function service_section_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function service_section_is_managed_upload(string $path): bool
{
    return service_section_starts_with(ltrim($path, '/'), 'uploads/site/');
}

function service_section_starts_with(string $haystack, string $needle): bool
{
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

$definitions = service_section_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_editor_image') {
    csrf_verify();
    $slug = (string)($_POST['section_slug'] ?? '');
    if (!isset($definitions[$slug])) {
        service_section_json(['ok' => false, 'error' => 'La seccion no es valida.'], 400);
    }

    if (!isset($_FILES['editor_image']) || (int)($_FILES['editor_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        service_section_json(['ok' => false, 'error' => 'No se pudo subir la imagen.'], 400);
    }

    $validationError = null;
    if (!image_validate_upload($_FILES['editor_image']['tmp_name'], (int)$_FILES['editor_image']['size'], $validationError)) {
        service_section_json(['ok' => false, 'error' => 'La imagen no es valida: ' . $validationError . '.'], 400);
    }

    $path = image_process_about_content($_FILES['editor_image']['tmp_name']);
    if ($path === false) {
        service_section_json(['ok' => false, 'error' => 'No se pudo procesar la imagen.'], 500);
    }

    service_section_json(['ok' => true, 'url' => BASE_URL . '/' . ltrim($path, '/')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $slug = (string)($_POST['section_slug'] ?? '');
    if (!isset($definitions[$slug])) {
        flash_set('err', 'La seccion no es valida.');
        header('Location: ' . ADMIN_URL . '/service-sections.php');
        exit;
    }

    $settings = service_section_load($db, $slug);
    $prefix = service_section_prefix($slug);
    $title = trim((string)($_POST['section_title'] ?? ''));
    $intro = trim((string)($_POST['section_intro'] ?? ''));
    $content = service_section_sanitize_html((string)($_POST['section_content_html'] ?? ''));
    $coverPath = trim((string)$settings['cover_path']);

    if ($title === '' || $content === '') {
        flash_set('err', 'El titulo y el contenido son obligatorios.');
        header('Location: ' . ADMIN_URL . '/service-sections.php?section=' . rawurlencode($slug));
        exit;
    }

    if (isset($_FILES['section_cover']) && (int)($_FILES['section_cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int)($_FILES['section_cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['section_cover']['tmp_name'])) {
            flash_set('err', 'No se pudo subir la portada.');
            header('Location: ' . ADMIN_URL . '/service-sections.php?section=' . rawurlencode($slug));
            exit;
        }

        $validationError = null;
        if (!image_validate_upload($_FILES['section_cover']['tmp_name'], (int)$_FILES['section_cover']['size'], $validationError)) {
            flash_set('err', 'La portada no es valida: ' . $validationError . '.');
            header('Location: ' . ADMIN_URL . '/service-sections.php?section=' . rawurlencode($slug));
            exit;
        }

        $processedPath = image_process_about_cover($_FILES['section_cover']['tmp_name']);
        if ($processedPath === false) {
            flash_set('err', 'No se pudo procesar la portada.');
            header('Location: ' . ADMIN_URL . '/service-sections.php?section=' . rawurlencode($slug));
            exit;
        }

        if ($coverPath !== '' && service_section_is_managed_upload($coverPath)) {
            $oldFile = ROOT_PATH . '/' . ltrim($coverPath, '/');
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $coverPath = $processedPath;
    }

    service_section_setting($db, $prefix . 'title', $title);
    service_section_setting($db, $prefix . 'intro', $intro);
    service_section_setting($db, $prefix . 'cover_path', $coverPath);
    service_section_setting($db, $prefix . 'content_html', $content);

    flash_set('ok', 'Seccion actualizada.');
    header('Location: ' . ADMIN_URL . '/service-sections.php?section=' . rawurlencode($slug));
    exit;
}

$activeSlug = (string)($_GET['section'] ?? 'alquileres');
if (!isset($definitions[$activeSlug])) {
    $activeSlug = 'alquileres';
}
$settings = service_section_load($db, $activeSlug);
$coverUrl = $settings['cover_path'] !== '' ? BASE_URL . '/' . ltrim($settings['cover_path'], '/') : '';

layout_head('Secciones de servicios', '<style>
.service-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
.service-tab{display:inline-flex;align-items:center;padding:8px 14px;border:1px solid #d8d2c9;border-radius:999px;background:white;color:var(--text);font-size:13px;font-weight:600;text-decoration:none}
.service-tab.active{background:var(--green);border-color:var(--green);color:#fff}
.service-admin-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:20px;align-items:start}
.section-cover-preview{overflow:hidden;border:1px solid #ece7dd;border-radius:12px;background:var(--gray-l)}
.section-cover-preview img{display:block;width:100%;aspect-ratio:16/7;object-fit:cover}
.editor-toolbar{display:flex;gap:6px;flex-wrap:wrap;padding:10px;border:1px solid #d8d2c9;border-bottom:0;border-radius:8px 8px 0 0;background:var(--gray-l)}
.editor-toolbar button,.editor-toolbar label{height:32px;min-width:34px;border:1px solid #d8d2c9;border-radius:6px;background:white;color:var(--text);font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;padding:0 9px;cursor:pointer}
.editor-toolbar button:hover,.editor-toolbar label:hover{border-color:var(--green);color:var(--green)}
.rich-editor{min-height:420px;border:1px solid #d8d2c9;border-radius:0 0 8px 8px;background:white;padding:18px;font-size:15px;line-height:1.75;outline:none}
.rich-editor:focus{border-color:var(--green)}
.rich-editor h2,.rich-editor h3,.rich-editor h4{line-height:1.25;margin:18px 0 8px;color:var(--text)}
.rich-editor p{margin:0 0 13px}
.rich-editor ul,.rich-editor ol{margin:0 0 14px 22px}
.rich-editor img{display:block;max-width:100%;height:auto;border-radius:10px;margin:16px 0}
.rich-editor figure.is-selected{outline:2px solid var(--green);outline-offset:4px;border-radius:10px}
.editor-image-tools{display:none;gap:8px;align-items:center;margin-top:10px;padding:10px;border:1px solid #d8d2c9;border-radius:8px;background:var(--gray-l)}
.editor-image-tools.is-visible{display:flex}
.editor-image-tools span{font-size:12px;color:var(--gray-d);font-weight:600;margin-right:auto}
.editor-image-tools button{height:30px;border:1px solid #d8d2c9;border-radius:6px;background:white;color:var(--text);font-size:12px;font-weight:700;padding:0 10px;cursor:pointer}
.editor-image-tools button:disabled{opacity:.45;cursor:not-allowed}
.editor-status{font-size:12px;color:var(--gray-m);margin-top:8px;min-height:18px}
.section-preview-card{position:sticky;top:82px}
.section-page-preview{border:1px solid #ece7dd;border-radius:12px;overflow:hidden;background:var(--cream)}
.section-page-preview-cover{height:170px;background-size:cover;background-position:center}
.section-page-preview-body{padding:18px}
.section-page-preview-body h4{font-size:22px;line-height:1.15;margin-bottom:8px;color:var(--green)}
.section-page-preview-body p{font-size:13px;color:var(--gray-d);line-height:1.6}
@media(max-width:1050px){.service-admin-grid{grid-template-columns:1fr}.section-preview-card{position:static}}
</style>');
layout_sidebar('service-sections.php');
?>
<div class="main">
<?php layout_topbar('Secciones de servicios', [
    ['href' => BASE_URL . $settings['url'], 'label' => 'Ver seccion', 'class' => 'btn btn-outline btn-sm'],
]); ?>
<div class="content">
<?php layout_flash(); ?>

<nav class="service-tabs">
  <?php foreach ($definitions as $slug => $definition): ?>
    <a href="<?= ADMIN_URL ?>/service-sections.php?section=<?= h($slug) ?>" class="service-tab <?= $slug === $activeSlug ? 'active' : '' ?>"><?= h($definition['label']) ?></a>
  <?php endforeach; ?>
</nav>

<form method="POST" enctype="multipart/form-data" id="sectionForm" class="service-admin-grid">
  <?= csrf_input() ?>
  <input type="hidden" name="section_slug" id="sectionSlug" value="<?= h($activeSlug) ?>">
  <div class="card">
    <div class="card-header">
      <h3><?= h($definitions[$activeSlug]['label']) ?></h3>
      <span class="text-sm text-m">Portada, titulo y editor visual</span>
    </div>
    <div class="card-body">
      <div class="form-grid">
        <?php if ($coverUrl !== ''): ?>
          <div class="form-group form-full">
            <label>Portada actual</label>
            <div class="section-cover-preview">
              <img src="<?= h($coverUrl) ?>" alt="Portada actual">
            </div>
          </div>
        <?php endif; ?>

        <div class="form-group form-full">
          <label for="section_cover">Imagen de portada</label>
          <input type="file" id="section_cover" name="section_cover" accept="image/*">
          <span class="form-hint">Recomendado: imagen horizontal. Si no subis una nueva, se mantiene la actual.</span>
        </div>

        <div class="form-group">
          <label for="section_title">Titulo</label>
          <input type="text" id="section_title" name="section_title" value="<?= h((string)$settings['title']) ?>">
        </div>

        <div class="form-group">
          <label for="section_intro">Bajada</label>
          <input type="text" id="section_intro" name="section_intro" value="<?= h((string)$settings['intro']) ?>">
        </div>

        <div class="form-group form-full">
          <label>Texto extendido</label>
          <div class="editor-toolbar" aria-label="Herramientas del editor">
            <button type="button" data-command="bold">B</button>
            <button type="button" data-command="italic">I</button>
            <button type="button" data-command="underline">U</button>
            <button type="button" data-block="h2">H2</button>
            <button type="button" data-block="h3">H3</button>
            <button type="button" data-block="p">P</button>
            <button type="button" data-command="insertUnorderedList">Lista</button>
            <button type="button" data-command="insertOrderedList">1.</button>
            <button type="button" id="sectionLinkButton">Link</button>
            <label for="sectionEditorImage" id="sectionEditorImageButton">Imagen</label>
            <input type="file" id="sectionEditorImage" accept="image/*" style="display:none;">
          </div>
          <div id="sectionEditor" class="rich-editor" contenteditable="true"><?= (string)$settings['content_html'] ?></div>
          <input type="hidden" name="section_content_html" id="sectionContentInput">
          <div class="editor-image-tools" id="sectionImageTools">
            <span>Imagen seleccionada</span>
            <button type="button" id="sectionImageUp">Subir</button>
            <button type="button" id="sectionImageDown">Bajar</button>
          </div>
          <div class="editor-status" id="sectionEditorStatus"></div>
          <span class="form-hint">Podes dar formato al texto, agregar links e insertar imagenes dentro del contenido.</span>
        </div>

        <div class="form-group form-full">
          <button type="submit" class="btn btn-primary">Guardar seccion</button>
        </div>
      </div>
    </div>
  </div>

  <aside class="card section-preview-card">
    <div class="card-header">
      <h3>Vista rapida</h3>
      <span class="text-sm text-m">Referencia visual</span>
    </div>
    <div class="card-body">
      <div class="section-page-preview">
        <div class="section-page-preview-cover" style="background-image:url('<?= h($coverUrl) ?>')"></div>
        <div class="section-page-preview-body">
          <h4 id="sectionPreviewTitle"><?= h((string)$settings['title']) ?></h4>
          <p id="sectionPreviewIntro"><?= h((string)$settings['intro']) ?></p>
        </div>
      </div>
    </div>
  </aside>
</form>

</div>
</div>

<script>
const csrfToken = <?= json_encode(csrf_token()) ?>;
const sectionForm = document.getElementById('sectionForm');
const editor = document.getElementById('sectionEditor');
const contentInput = document.getElementById('sectionContentInput');
const statusEl = document.getElementById('sectionEditorStatus');
const titleInput = document.getElementById('section_title');
const introInput = document.getElementById('section_intro');
const sectionSlug = document.getElementById('sectionSlug').value;
const imageTools = document.getElementById('sectionImageTools');
const imageUpButton = document.getElementById('sectionImageUp');
const imageDownButton = document.getElementById('sectionImageDown');
let savedEditorRange = null;
let selectedFigure = null;
let imageInsertMarker = null;

function selectionBelongsToEditor(selection) {
  if (!selection || selection.rangeCount === 0) return false;
  const range = selection.getRangeAt(0);
  return editor.contains(range.commonAncestorContainer);
}

function saveEditorSelection() {
  const selection = window.getSelection();
  if (!selectionBelongsToEditor(selection)) return;
  savedEditorRange = selection.getRangeAt(0).cloneRange();
}

function restoreEditorSelection() {
  editor.focus();
  const selection = window.getSelection();
  selection.removeAllRanges();

  if (savedEditorRange) {
    selection.addRange(savedEditorRange);
    return savedEditorRange;
  }

  const range = document.createRange();
  range.selectNodeContents(editor);
  range.collapse(false);
  selection.addRange(range);
  savedEditorRange = range.cloneRange();
  return range;
}

function insertEditorHtml(html) {
  const range = restoreEditorSelection();
  range.deleteContents();

  const fragment = range.createContextualFragment(html);
  const lastNode = fragment.lastChild;
  range.insertNode(fragment);

  if (lastNode) {
    range.setStartAfter(lastNode);
    range.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    savedEditorRange = range.cloneRange();
  }
}

function removeImageInsertMarker() {
  if (imageInsertMarker && imageInsertMarker.parentNode) {
    imageInsertMarker.parentNode.removeChild(imageInsertMarker);
  }
  imageInsertMarker = null;
}

function placeImageInsertMarker() {
  const range = restoreEditorSelection();
  removeImageInsertMarker();
  imageInsertMarker = document.createElement('span');
  imageInsertMarker.setAttribute('data-editor-image-marker', '1');
  imageInsertMarker.style.display = 'none';
  range.insertNode(imageInsertMarker);
  range.setStartAfter(imageInsertMarker);
  range.collapse(true);
  savedEditorRange = range.cloneRange();
}

function insertImageAtMarker(html) {
  if (!imageInsertMarker || !imageInsertMarker.parentNode) {
    insertEditorHtml(html);
    return;
  }

  const range = document.createRange();
  range.setStartBefore(imageInsertMarker);
  const fragment = range.createContextualFragment(html);
  const lastNode = fragment.lastChild;
  imageInsertMarker.parentNode.insertBefore(fragment, imageInsertMarker);
  removeImageInsertMarker();

  if (lastNode) {
    range.setStartAfter(lastNode);
    range.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    savedEditorRange = range.cloneRange();
    if (lastNode.matches && lastNode.matches('figure')) {
      selectEditorFigure(lastNode);
    }
  }
}

function editorTopLevelBlock(node) {
  let current = node;
  while (current && current.parentNode !== editor) {
    current = current.parentNode;
  }
  return current && current.parentNode === editor ? current : null;
}

function editorElementSibling(node, direction) {
  const topLevel = editorTopLevelBlock(node) || node;
  let sibling = direction === 'previous' ? topLevel.previousSibling : topLevel.nextSibling;
  while (sibling && sibling.nodeType === Node.TEXT_NODE && sibling.textContent.trim() === '') {
    sibling = direction === 'previous' ? sibling.previousSibling : sibling.nextSibling;
  }
  return sibling;
}

function selectEditorFigure(figure) {
  if (selectedFigure) {
    selectedFigure.classList.remove('is-selected');
  }
  selectedFigure = figure;
  if (!selectedFigure) {
    imageTools.classList.remove('is-visible');
    return;
  }
  selectedFigure.classList.add('is-selected');
  imageTools.classList.add('is-visible');
  imageUpButton.disabled = !editorElementSibling(selectedFigure, 'previous');
  imageDownButton.disabled = !editorElementSibling(selectedFigure, 'next');
}

editor.addEventListener('click', (event) => {
  const figure = event.target.closest('figure');
  selectEditorFigure(figure && editor.contains(figure) ? figure : null);
});

imageUpButton.addEventListener('click', () => {
  if (!selectedFigure) return;
  const previous = editorElementSibling(selectedFigure, 'previous');
  if (!previous) return;
  editor.insertBefore(selectedFigure, previous);
  selectEditorFigure(selectedFigure);
  saveEditorSelection();
});

imageDownButton.addEventListener('click', () => {
  if (!selectedFigure) return;
  const next = editorElementSibling(selectedFigure, 'next');
  if (!next) return;
  editor.insertBefore(selectedFigure, next.nextSibling);
  selectEditorFigure(selectedFigure);
  saveEditorSelection();
});

function editorCommand(command, value = null) {
  restoreEditorSelection();
  document.execCommand(command, false, value);
  saveEditorSelection();
}

['keyup', 'mouseup', 'input', 'focus'].forEach((eventName) => {
  editor.addEventListener(eventName, saveEditorSelection);
});

document.querySelectorAll('[data-command]').forEach((button) => {
  button.addEventListener('click', () => editorCommand(button.dataset.command));
});

document.querySelectorAll('[data-block]').forEach((button) => {
  button.addEventListener('click', () => editorCommand('formatBlock', button.dataset.block));
});

document.getElementById('sectionLinkButton').addEventListener('click', () => {
  const url = window.prompt('URL del link');
  if (!url) return;
  editorCommand('createLink', url);
});

document.getElementById('sectionEditorImageButton').addEventListener('mousedown', () => {
  placeImageInsertMarker();
});

document.getElementById('sectionEditorImage').addEventListener('change', async function () {
  const file = this.files[0];
  this.value = '';
  if (!file) {
    removeImageInsertMarker();
    return;
  }

  const formData = new FormData();
  formData.append('action', 'upload_editor_image');
  formData.append('csrf', csrfToken);
  formData.append('section_slug', sectionSlug);
  formData.append('editor_image', file);
  statusEl.textContent = 'Subiendo imagen...';

  try {
    const response = await fetch('service-sections.php', { method: 'POST', body: formData });
    const data = await response.json();
    if (!data.ok) {
      statusEl.textContent = data.error || 'No se pudo subir la imagen.';
      return;
    }
    insertImageAtMarker('<figure><img src="' + data.url + '" alt=""><figcaption></figcaption></figure>');
    statusEl.textContent = 'Imagen insertada.';
  } catch (error) {
    removeImageInsertMarker();
    statusEl.textContent = 'Error de red al subir la imagen.';
  }
});

titleInput.addEventListener('input', () => {
  document.getElementById('sectionPreviewTitle').textContent = titleInput.value || '';
});

introInput.addEventListener('input', () => {
  document.getElementById('sectionPreviewIntro').textContent = introInput.value || '';
});

sectionForm.addEventListener('submit', () => {
  removeImageInsertMarker();
  contentInput.value = editor.innerHTML.trim();
});
</script>
<?php layout_foot(); ?>
