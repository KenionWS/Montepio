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

function popup_link_is_valid(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (popup_starts_with($value, '#') || popup_starts_with($value, '/')) {
        return true;
    }

    return (bool)preg_match('~^https?://~i', $value);
}

function popup_starts_with(string $haystack, string $needle): bool
{
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

function popup_frequency_options(): array
{
    return [
        'always' => 'Cada visita',
        'session' => 'Una vez por sesion',
        'daily' => 'Una vez por dia',
        'weekly' => 'Una vez por semana',
        'once' => 'Una sola vez',
    ];
}

function popup_is_managed_upload(string $path): bool
{
    return popup_starts_with(ltrim($path, '/'), 'uploads/site/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? 'save');

    $stmt = $db->prepare("SELECT * FROM site_popup WHERE id = 1");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $imagePath = trim((string)($existing['image_path'] ?? ''));

    if ($action === 'remove_image') {
        if ($imagePath !== '' && popup_is_managed_upload($imagePath)) {
            $oldFile = ROOT_PATH . '/' . ltrim($imagePath, '/');
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $db->prepare("UPDATE site_popup SET image_path = NULL, updated_at = datetime('now') WHERE id = 1")->execute();
        flash_set('ok', 'Imagen eliminada.');
        header('Location: ' . ADMIN_URL . '/popup.php');
        exit;
    }

    $frequency = trim((string)($_POST['frequency'] ?? 'daily'));
    $frequencyOptions = popup_frequency_options();
    if (!isset($frequencyOptions[$frequency])) {
        $frequency = 'daily';
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $ctaText = trim((string)($_POST['cta_text'] ?? ''));
    $ctaLink = trim((string)($_POST['cta_link'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($isActive === 1 && $title === '' && $description === '' && $imagePath === '' && (!isset($_FILES['popup_image']) || (int)($_FILES['popup_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
        flash_set('err', 'Para activar el popup carga al menos un titulo, descripcion o imagen.');
        header('Location: ' . ADMIN_URL . '/popup.php');
        exit;
    }

    if ($ctaText !== '' && $ctaLink === '') {
        flash_set('err', 'Si cargas texto para el CTA, tambien tenes que cargar un link.');
        header('Location: ' . ADMIN_URL . '/popup.php');
        exit;
    }

    if ($ctaLink !== '' && !popup_link_is_valid($ctaLink)) {
        flash_set('err', 'El link del CTA no es valido. Podes usar https://..., una ruta como /catalogo o un ancla como #ubicacion.');
        header('Location: ' . ADMIN_URL . '/popup.php');
        exit;
    }

    if (isset($_FILES['popup_image']) && (int)($_FILES['popup_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int)($_FILES['popup_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['popup_image']['tmp_name'])) {
            $validationError = null;
            if (!image_validate_upload($_FILES['popup_image']['tmp_name'], (int)$_FILES['popup_image']['size'], $validationError)) {
                flash_set('err', 'La imagen del popup no es valida: ' . $validationError . '.');
                header('Location: ' . ADMIN_URL . '/popup.php');
                exit;
            }

            $processedPath = image_process_site_popup($_FILES['popup_image']['tmp_name']);
            if ($processedPath === false) {
                flash_set('err', 'No se pudo procesar la imagen del popup.');
                header('Location: ' . ADMIN_URL . '/popup.php');
                exit;
            }

            if ($imagePath !== '' && popup_is_managed_upload($imagePath)) {
                $oldFile = ROOT_PATH . '/' . ltrim($imagePath, '/');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $imagePath = $processedPath;
        } else {
            flash_set('err', 'No se pudo subir la imagen del popup.');
            header('Location: ' . ADMIN_URL . '/popup.php');
            exit;
        }
    }

    $stmt = $db->prepare("
        UPDATE site_popup
        SET is_active = ?, frequency = ?, image_path = ?, title = ?, description = ?,
            cta_text = ?, cta_link = ?, updated_at = datetime('now')
        WHERE id = 1
    ");
    $stmt->execute([$isActive, $frequency, $imagePath !== '' ? $imagePath : null, $title, $description, $ctaText, $ctaLink]);

    flash_set('ok', 'Popup actualizado.');
    header('Location: ' . ADMIN_URL . '/popup.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM site_popup WHERE id = 1");
$stmt->execute();
$popup = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'is_active' => 0,
    'frequency' => 'daily',
    'image_path' => '',
    'title' => '',
    'description' => '',
    'cta_text' => '',
    'cta_link' => '',
];

$frequencyOptions = popup_frequency_options();
$imagePath = trim((string)($popup['image_path'] ?? ''));
$imageUrl = $imagePath !== '' ? BASE_URL . '/' . ltrim($imagePath, '/') : '';

layout_head('Popup');
layout_sidebar('popup.php');
?>
<div class="main">
<?php layout_topbar('Popup', [
    ['href' => BASE_URL . '/', 'label' => 'Ver sitio', 'class' => 'btn btn-outline btn-sm'],
]); ?>
<div class="content">
<?php layout_flash(); ?>

<div class="popup-admin-grid">
  <div class="card">
    <div class="card-header">
      <h3>Configuracion del popup</h3>
      <span class="badge <?= (int)($popup['is_active'] ?? 0) === 1 ? 'badge-green' : 'badge-gray' ?>"><?= (int)($popup['is_active'] ?? 0) === 1 ? 'Activo' : 'Oculto' ?></span>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" class="form-grid">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save">

        <div class="form-group">
          <label>Estado</label>
          <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" <?= (int)($popup['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>Mostrar popup en el sitio</span>
          </label>
        </div>

        <div class="form-group">
          <label for="frequency">Frecuencia</label>
          <select id="frequency" name="frequency">
            <?php foreach ($frequencyOptions as $key => $label): ?>
              <option value="<?= h($key) ?>" <?= (string)($popup['frequency'] ?? 'daily') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">La frecuencia se guarda en el navegador del visitante.</span>
        </div>

        <div class="form-group form-full">
          <label for="popup_image">Imagen de fondo</label>
          <input type="file" id="popup_image" name="popup_image" accept="image/*">
          <span class="form-hint">Opcional. Si no subis imagen, el popup usa fondo claro.</span>
        </div>

        <div class="form-group">
          <label for="title">Titulo</label>
          <input type="text" id="title" name="title" value="<?= h((string)($popup['title'] ?? '')) ?>" placeholder="Nueva coleccion disponible">
        </div>

        <div class="form-group">
          <label for="cta_text">CTA: texto</label>
          <input type="text" id="cta_text" name="cta_text" value="<?= h((string)($popup['cta_text'] ?? '')) ?>" placeholder="Ver catalogo">
        </div>

        <div class="form-group form-full">
          <label for="description">Descripcion</label>
          <textarea id="description" name="description" rows="4" placeholder="Texto breve del popup"><?= h((string)($popup['description'] ?? '')) ?></textarea>
        </div>

        <div class="form-group form-full">
          <label for="cta_link">CTA: link</label>
          <input type="text" id="cta_link" name="cta_link" value="<?= h((string)($popup['cta_link'] ?? '')) ?>" placeholder="/catalogo">
          <span class="form-hint">Puede ser `https://...`, `/catalogo` o un ancla como `#ubicacion`.</span>
        </div>

        <div class="form-group form-full">
          <button type="submit" class="btn btn-primary">Guardar popup</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Vista previa</h3>
      <span class="text-sm text-m"><?= h($frequencyOptions[(string)($popup['frequency'] ?? 'daily')] ?? 'Una vez por dia') ?></span>
    </div>
    <div class="card-body">
      <div class="popup-preview <?= $imageUrl !== '' ? 'has-image' : '' ?>">
        <?php if ($imageUrl !== ''): ?>
          <div class="popup-preview-media" style="background-image:url('<?= h($imageUrl) ?>')"></div>
        <?php endif; ?>
        <div class="popup-preview-content">
          <span class="popup-preview-kicker">Montepio</span>
          <h3><?= h(trim((string)($popup['title'] ?? '')) ?: 'Titulo del popup') ?></h3>
          <p><?= h(trim((string)($popup['description'] ?? '')) ?: 'Descripcion breve para mostrar a los visitantes.') ?></p>
          <?php if (trim((string)($popup['cta_text'] ?? '')) !== ''): ?>
            <span class="popup-preview-cta"><?= h((string)$popup['cta_text']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($imageUrl !== ''): ?>
        <form method="POST" class="popup-remove-image" onsubmit="return confirm('Eliminar la imagen del popup?')">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="remove_image">
          <button type="submit" class="btn btn-outline btn-sm">Eliminar imagen</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

</div>
</div>

<style>
.popup-admin-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.7fr);gap:20px;align-items:start}
.checkbox-line{display:flex;align-items:center;gap:10px;font-weight:400}
.checkbox-line input{width:auto}
.popup-preview{position:relative;min-height:360px;border-radius:18px;overflow:hidden;background:var(--white);border:1px solid #ece7dd;display:grid;grid-template-columns:1fr}
.popup-preview.has-image{grid-template-columns:minmax(160px,.9fr) 1fr}
.popup-preview-media{min-height:360px;background-size:contain;background-repeat:no-repeat;background-position: center;}
.popup-preview-content{position:relative;z-index:1;padding:32px 28px;color:var(--green-dark);display:flex;flex-direction:column;justify-content:center;align-items:flex-start}
.popup-preview-kicker{font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--gold)}
.popup-preview h3{font-family:'Playfair Display',serif;font-size:34px;line-height:1.05;margin:8px 0 10px}
.popup-preview p{font-size:14px;line-height:1.55;max-width:34ch;margin:0 0 18px}
.popup-preview-cta{display:inline-flex;padding:9px 15px;border-radius:7px;background:var(--green);color:white;font-size:13px;font-weight:700}
.popup-remove-image{margin-top:14px}
@media(max-width:1000px){.popup-admin-grid{grid-template-columns:1fr}}
@media(max-width:640px){.popup-preview.has-image{grid-template-columns:1fr}.popup-preview-media{min-height:220px}}
</style>
<?php layout_foot(); ?>
