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

function about_default_content(): string
{
    return '<h2>Una casa con historia en Buenos Aires</h2><p>Desde 1985 nos especializamos en la compra, venta, alquiler y restauracion de antiguedades y muebles unicos en el corazon de Flores, CABA.</p><p>Montepio es una casa familiar donde cada pieza se elige, se conserva y se ofrece con criterio de oficio. Trabajamos con muebles, objetos decorativos, luminarias, arte, piezas para ambientaciones y restauraciones a medida.</p><p>Nuestro recorrido combina experiencia, taller propio y atencion cercana para acompanar a quienes buscan una pieza especial, necesitan tasar un objeto o quieren recuperar el valor de un mueble con historia.</p>';
}

function about_defaults(): array
{
    return [
        'about_title' => 'Quienes somos',
        'about_intro' => 'Una version extendida de nuestra historia, el oficio y la forma en que trabajamos cada pieza.',
        'about_cover_path' => 'assets/brand/fachada-montepio.jpg',
        'about_content_html' => about_default_content(),
    ];
}

function about_settings(PDO $db): array
{
    $defaults = about_defaults();
    $keys = "'" . implode("','", array_keys($defaults)) . "'";
    $rows = $db->query("
        SELECT setting_key, setting_value
        FROM site_settings
        WHERE setting_key IN ($keys)
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    return array_replace($defaults, array_filter($rows, static fn($value): bool => $value !== null && $value !== ''));
}

function about_save_setting(PDO $db, string $key, string $value): void
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

function about_sanitize_html(string $html): string
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

function about_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_editor_image') {
    csrf_verify();

    if (!isset($_FILES['editor_image']) || (int)($_FILES['editor_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        about_json_response(['ok' => false, 'error' => 'No se pudo subir la imagen.'], 400);
    }

    $validationError = null;
    if (!image_validate_upload($_FILES['editor_image']['tmp_name'], (int)$_FILES['editor_image']['size'], $validationError)) {
        about_json_response(['ok' => false, 'error' => 'La imagen no es valida: ' . $validationError . '.'], 400);
    }

    $path = image_process_about_content($_FILES['editor_image']['tmp_name']);
    if ($path === false) {
        about_json_response(['ok' => false, 'error' => 'No se pudo procesar la imagen.'], 500);
    }

    about_json_response(['ok' => true, 'url' => BASE_URL . '/' . ltrim($path, '/')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $settings = about_settings($db);
    $title = trim((string)($_POST['about_title'] ?? ''));
    $intro = trim((string)($_POST['about_intro'] ?? ''));
    $content = about_sanitize_html((string)($_POST['about_content_html'] ?? ''));
    $coverPath = trim((string)$settings['about_cover_path']);

    if ($title === '') {
        flash_set('err', 'El titulo es obligatorio.');
        header('Location: ' . ADMIN_URL . '/about.php');
        exit;
    }

    if ($content === '') {
        flash_set('err', 'El contenido no puede quedar vacio.');
        header('Location: ' . ADMIN_URL . '/about.php');
        exit;
    }

    if (isset($_FILES['about_cover']) && (int)($_FILES['about_cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int)($_FILES['about_cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['about_cover']['tmp_name'])) {
            flash_set('err', 'No se pudo subir la portada.');
            header('Location: ' . ADMIN_URL . '/about.php');
            exit;
        }

        $validationError = null;
        if (!image_validate_upload($_FILES['about_cover']['tmp_name'], (int)$_FILES['about_cover']['size'], $validationError)) {
            flash_set('err', 'La portada no es valida: ' . $validationError . '.');
            header('Location: ' . ADMIN_URL . '/about.php');
            exit;
        }

        $processedPath = image_process_about_cover($_FILES['about_cover']['tmp_name']);
        if ($processedPath === false) {
            flash_set('err', 'No se pudo procesar la portada.');
            header('Location: ' . ADMIN_URL . '/about.php');
            exit;
        }

        if ($coverPath !== '' && str_starts_with(ltrim($coverPath, '/'), 'uploads/site/')) {
            $oldFile = ROOT_PATH . '/' . ltrim($coverPath, '/');
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $coverPath = $processedPath;
    }

    about_save_setting($db, 'about_title', $title);
    about_save_setting($db, 'about_intro', $intro);
    about_save_setting($db, 'about_cover_path', $coverPath);
    about_save_setting($db, 'about_content_html', $content);

    flash_set('ok', 'Seccion Quienes somos actualizada.');
    header('Location: ' . ADMIN_URL . '/about.php');
    exit;
}

$settings = about_settings($db);
$coverUrl = trim((string)$settings['about_cover_path']) !== '' ? BASE_URL . '/' . ltrim((string)$settings['about_cover_path'], '/') : '';

layout_head('Quienes somos', '<style>
.about-admin-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:20px;align-items:start}
.about-cover-preview{overflow:hidden;border:1px solid #ece7dd;border-radius:12px;background:var(--gray-l)}
.about-cover-preview img{display:block;width:100%;aspect-ratio:16/7;object-fit:cover}
.editor-toolbar{display:flex;gap:6px;flex-wrap:wrap;padding:10px;border:1px solid #d8d2c9;border-bottom:0;border-radius:8px 8px 0 0;background:var(--gray-l)}
.editor-toolbar button,.editor-toolbar label{height:32px;min-width:34px;border:1px solid #d8d2c9;border-radius:6px;background:white;color:var(--text);font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;padding:0 9px;cursor:pointer}
.editor-toolbar button:hover,.editor-toolbar label:hover{border-color:var(--green);color:var(--green)}
.rich-editor{min-height:420px;border:1px solid #d8d2c9;border-radius:0 0 8px 8px;background:white;padding:18px;font-size:15px;line-height:1.75;outline:none}
.rich-editor:focus{border-color:var(--green)}
.rich-editor h2,.rich-editor h3,.rich-editor h4{line-height:1.25;margin:18px 0 8px;color:var(--text)}
.rich-editor p{margin:0 0 13px}
.rich-editor ul,.rich-editor ol{margin:0 0 14px 22px}
.rich-editor img{display:block;max-width:100%;height:auto;border-radius:10px;margin:16px 0}
.editor-status{font-size:12px;color:var(--gray-m);margin-top:8px;min-height:18px}
.about-preview-card{position:sticky;top:82px}
.about-page-preview{border:1px solid #ece7dd;border-radius:12px;overflow:hidden;background:var(--cream)}
.about-page-preview-cover{height:170px;background-size:cover;background-position:center}
.about-page-preview-body{padding:18px}
.about-page-preview-body h4{font-size:22px;line-height:1.15;margin-bottom:8px;color:var(--green)}
.about-page-preview-body p{font-size:13px;color:var(--gray-d);line-height:1.6}
@media(max-width:1050px){.about-admin-grid{grid-template-columns:1fr}.about-preview-card{position:static}}
</style>');
layout_sidebar('about.php');
?>
<div class="main">
<?php layout_topbar('Quienes somos', [
    ['href' => BASE_URL . '/quienes-somos', 'label' => 'Ver seccion', 'class' => 'btn btn-outline btn-sm'],
]); ?>
<div class="content">
<?php layout_flash(); ?>

<form method="POST" enctype="multipart/form-data" id="aboutForm" class="about-admin-grid">
  <?= csrf_input() ?>
  <div class="card">
    <div class="card-header">
      <h3>Contenido de la seccion</h3>
      <span class="text-sm text-m">Portada, titulo y editor visual</span>
    </div>
    <div class="card-body">
      <div class="form-grid">
        <?php if ($coverUrl !== ''): ?>
          <div class="form-group form-full">
            <label>Portada actual</label>
            <div class="about-cover-preview">
              <img src="<?= h($coverUrl) ?>" alt="Portada actual">
            </div>
          </div>
        <?php endif; ?>

        <div class="form-group form-full">
          <label for="about_cover">Imagen de portada</label>
          <input type="file" id="about_cover" name="about_cover" accept="image/*">
          <span class="form-hint">Recomendado: imagen horizontal. Si no subis una nueva, se mantiene la actual.</span>
        </div>

        <div class="form-group">
          <label for="about_title">Titulo</label>
          <input type="text" id="about_title" name="about_title" value="<?= h((string)$settings['about_title']) ?>">
        </div>

        <div class="form-group">
          <label for="about_intro">Bajada</label>
          <input type="text" id="about_intro" name="about_intro" value="<?= h((string)$settings['about_intro']) ?>">
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
            <button type="button" id="aboutLinkButton">Link</button>
            <label for="aboutEditorImage">Imagen</label>
            <input type="file" id="aboutEditorImage" accept="image/*" style="display:none;">
          </div>
          <div id="aboutEditor" class="rich-editor" contenteditable="true"><?= (string)$settings['about_content_html'] ?></div>
          <input type="hidden" name="about_content_html" id="aboutContentInput">
          <div class="editor-status" id="aboutEditorStatus"></div>
          <span class="form-hint">Podes dar formato al texto, agregar links e insertar imagenes dentro del contenido.</span>
        </div>

        <div class="form-group form-full">
          <button type="submit" class="btn btn-primary">Guardar seccion</button>
        </div>
      </div>
    </div>
  </div>

  <aside class="card about-preview-card">
    <div class="card-header">
      <h3>Vista rapida</h3>
      <span class="text-sm text-m">Referencia visual</span>
    </div>
    <div class="card-body">
      <div class="about-page-preview">
        <div class="about-page-preview-cover" style="background-image:url('<?= h($coverUrl) ?>')"></div>
        <div class="about-page-preview-body">
          <h4 id="aboutPreviewTitle"><?= h((string)$settings['about_title']) ?></h4>
          <p id="aboutPreviewIntro"><?= h((string)$settings['about_intro']) ?></p>
        </div>
      </div>
    </div>
  </aside>
</form>

</div>
</div>

<script>
const csrfToken = <?= json_encode(csrf_token()) ?>;
const aboutForm = document.getElementById('aboutForm');
const editor = document.getElementById('aboutEditor');
const contentInput = document.getElementById('aboutContentInput');
const statusEl = document.getElementById('aboutEditorStatus');
const titleInput = document.getElementById('about_title');
const introInput = document.getElementById('about_intro');
let savedEditorRange = null;

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

document.getElementById('aboutLinkButton').addEventListener('click', () => {
  const url = window.prompt('URL del link');
  if (!url) return;
  editorCommand('createLink', url);
});

document.getElementById('aboutEditorImage').addEventListener('change', async function () {
  restoreEditorSelection();
  const file = this.files[0];
  this.value = '';
  if (!file) return;

  const formData = new FormData();
  formData.append('action', 'upload_editor_image');
  formData.append('csrf', csrfToken);
  formData.append('editor_image', file);
  statusEl.textContent = 'Subiendo imagen...';

  try {
    const response = await fetch('about.php', { method: 'POST', body: formData });
    const data = await response.json();
    if (!data.ok) {
      statusEl.textContent = data.error || 'No se pudo subir la imagen.';
      return;
    }
    insertEditorHtml('<figure><img src="' + data.url + '" alt=""><figcaption></figcaption></figure>');
    statusEl.textContent = 'Imagen insertada.';
  } catch (error) {
    statusEl.textContent = 'Error de red al subir la imagen.';
  }
});

titleInput.addEventListener('input', () => {
  document.getElementById('aboutPreviewTitle').textContent = titleInput.value || 'Quienes somos';
});

introInput.addEventListener('input', () => {
  document.getElementById('aboutPreviewIntro').textContent = introInput.value || '';
});

aboutForm.addEventListener('submit', () => {
  contentInput.value = editor.innerHTML.trim();
});
</script>
<?php layout_foot(); ?>
