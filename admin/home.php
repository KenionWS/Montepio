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

function hero_admin_link_is_valid(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (str_starts_with($value, '#') || str_starts_with($value, '/')) {
        return true;
    }

    return (bool)preg_match('~^https?://~i', $value);
}

function contact_admin_link_is_valid(string $value): bool
{
    if (hero_admin_link_is_valid($value)) {
        return true;
    }

    return (bool)preg_match('~^(tel|mailto):~i', $value);
}

function contact_admin_defaults(): array
{
    return [
        'title' => 'Visitanos',
        'items' => [
            'address' => ['label' => 'Direccion', 'value' => "Av. Rivadavia 7701, Flores\nCiudad de Buenos Aires", 'link' => 'https://maps.app.goo.gl/7YhnpWUrzZuzrprr9'],
            'hours' => ['label' => 'Horarios de atencion', 'value' => "Lunes a viernes de 9 a 18\nSabados de 9 a 17", 'link' => ''],
            'phones' => ['label' => 'Telefonos', 'value' => '4612-1221 / 4612-8787', 'link' => 'tel:46121221'],
            'whatsapp' => ['label' => 'WhatsApp', 'value' => '116571-4568', 'link' => 'https://wa.me/5491165714568'],
            'email' => ['label' => 'Email', 'value' => 'montepioantiguedades@gmail.com', 'link' => 'mailto:montepioantiguedades@gmail.com'],
            'instagram' => ['label' => 'Instagram', 'value' => 'Seguinos en Instagram', 'link' => 'https://www.instagram.com/'],
        ],
    ];
}

function contact_admin_settings(PDO $db): array
{
    $defaults = contact_admin_defaults();
    $keys = ['home_contact_title'];
    foreach (array_keys($defaults['items']) as $key) {
        $keys[] = 'home_contact_' . $key . '_label';
        $keys[] = 'home_contact_' . $key . '_value';
        $keys[] = 'home_contact_' . $key . '_link';
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $items = [];
    foreach ($defaults['items'] as $key => $item) {
        $items[$key] = [
            'label' => trim((string)($rows['home_contact_' . $key . '_label'] ?? $item['label'])),
            'value' => trim((string)($rows['home_contact_' . $key . '_value'] ?? $item['value'])),
            'link' => trim((string)($rows['home_contact_' . $key . '_link'] ?? $item['link'])),
        ];
    }

    return [
        'title' => trim((string)($rows['home_contact_title'] ?? $defaults['title'])),
        'items' => $items,
    ];
}

function contact_admin_save_setting(PDO $db, string $key, string $value): void
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

function hero_admin_fetch_slides(PDO $db): array
{
    return $db->query("
        SELECT *
        FROM home_hero_slides
        ORDER BY position ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function service_admin_fetch_blocks(PDO $db): array
{
    return $db->query("
        SELECT *
        FROM home_service_blocks
        ORDER BY position ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function service_admin_style_options(): array
{
    return [
        'rent' => 'Alquileres',
        'buy' => 'Compra y venta',
        'restore' => 'Restauraciones',
        'default' => 'Verde neutro',
    ];
}

function hero_admin_is_managed_upload(string $path): bool
{
    return str_starts_with(ltrim($path, '/'), 'uploads/site/');
}

function hero_admin_ensure_default_slide(PDO $db): void
{
    $count = (int)$db->query("SELECT COUNT(*) FROM home_hero_slides")->fetchColumn();
    if ($count > 0) {
        return;
    }

    $rows = $db->query("
        SELECT setting_key, setting_value
        FROM site_settings
        WHERE setting_key IN (
            'home_hero_image_path',
            'home_hero_link',
            'home_hero_tag',
            'home_hero_title',
            'home_hero_description',
            'home_hero_button_primary_text',
            'home_hero_button_primary_link',
            'home_hero_button_secondary_text',
            'home_hero_button_secondary_link'
        )
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $db->prepare("
        INSERT INTO home_hero_slides (
            image_path, link_url, tag, title, description,
            button_primary_text, button_primary_link,
            button_secondary_text, button_secondary_link,
            position, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");

    $stmt->execute([
        trim((string)($rows['home_hero_image_path'] ?? '')) ?: 'assets/site/hero-principal.png',
        trim((string)($rows['home_hero_link'] ?? '')),
        trim((string)($rows['home_hero_tag'] ?? 'Casa Montepio - CABA')),
        trim((string)($rows['home_hero_title'] ?? 'Antiguedades con historia y estilo')),
        trim((string)($rows['home_hero_description'] ?? 'Compra, venta, alquiler y restauracion de muebles y objetos unicos. Mas de 40 anos en Buenos Aires.')),
        trim((string)($rows['home_hero_button_primary_text'] ?? 'Ver catalogo')),
        trim((string)($rows['home_hero_button_primary_link'] ?? '#categorias')),
        trim((string)($rows['home_hero_button_secondary_text'] ?? 'Como llegar')),
        trim((string)($rows['home_hero_button_secondary_link'] ?? '#ubicacion')),
        0,
        1,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save_contact') {
        $defaults = contact_admin_defaults();
        $title = trim((string)($_POST['contact_title'] ?? ''));

        if ($title === '') {
            flash_set('err', 'El titulo de Visitanos es obligatorio.');
            header('Location: ' . ADMIN_URL . '/home.php#visitanos-home');
            exit;
        }

        $items = [];
        foreach ($defaults['items'] as $key => $item) {
            $label = trim((string)($_POST['contact_' . $key . '_label'] ?? ''));
            $value = trim((string)($_POST['contact_' . $key . '_value'] ?? ''));
            $link = trim((string)($_POST['contact_' . $key . '_link'] ?? ''));

            if ($label === '' || $value === '') {
                flash_set('err', 'Cada item de Visitanos necesita titulo y valor.');
                header('Location: ' . ADMIN_URL . '/home.php#visitanos-home');
                exit;
            }

            if (!contact_admin_link_is_valid($link)) {
                flash_set('err', 'Hay un link no valido en Visitanos. Podes usar https://..., /ruta, tel:... o mailto:...');
                header('Location: ' . ADMIN_URL . '/home.php#visitanos-home');
                exit;
            }

            $items[$key] = compact('label', 'value', 'link');
        }

        contact_admin_save_setting($db, 'home_contact_title', $title);
        foreach ($items as $key => $item) {
            contact_admin_save_setting($db, 'home_contact_' . $key . '_label', $item['label']);
            contact_admin_save_setting($db, 'home_contact_' . $key . '_value', $item['value']);
            contact_admin_save_setting($db, 'home_contact_' . $key . '_link', $item['link']);
        }

        flash_set('ok', 'Bloque Visitanos actualizado.');
        header('Location: ' . ADMIN_URL . '/home.php#visitanos-home');
        exit;
    }

    if ($action === 'delete_service') {
        $blockId = max(0, (int)($_POST['block_id'] ?? 0));
        if ($blockId <= 0) {
            flash_set('err', 'No se encontro el bloque a eliminar.');
            header('Location: ' . ADMIN_URL . '/home.php#servicios-home');
            exit;
        }

        $stmt = $db->prepare("SELECT image_path FROM home_service_blocks WHERE id = ?");
        $stmt->execute([$blockId]);
        $block = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($block) {
            $db->prepare("DELETE FROM home_service_blocks WHERE id = ?")->execute([$blockId]);
            $imagePath = trim((string)($block['image_path'] ?? ''));
            if ($imagePath !== '' && hero_admin_is_managed_upload($imagePath)) {
                $oldFile = ROOT_PATH . '/' . ltrim($imagePath, '/');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            flash_set('ok', 'Bloque eliminado.');
        } else {
            flash_set('err', 'No se encontro el bloque a eliminar.');
        }

        header('Location: ' . ADMIN_URL . '/home.php#servicios-home');
        exit;
    }

    if ($action === 'save_service') {
        $blockId = max(0, (int)($_POST['block_id'] ?? 0));
        $serviceTitle = trim((string)($_POST['service_title'] ?? ''));
        $serviceDescription = trim((string)($_POST['service_description'] ?? ''));
        $serviceLink = trim((string)($_POST['service_link'] ?? ''));
        $styleKey = trim((string)($_POST['style_key'] ?? 'default'));
        $position = max(0, (int)($_POST['service_position'] ?? 0));
        $isActive = isset($_POST['service_is_active']) ? 1 : 0;
        $styleOptions = service_admin_style_options();

        if (!isset($styleOptions[$styleKey])) {
            $styleKey = 'default';
        }

        if ($serviceTitle === '') {
            flash_set('err', 'El titulo del bloque es obligatorio.');
            header('Location: ' . ADMIN_URL . '/home.php' . ($blockId > 0 ? '?edit_service=' . $blockId : '') . '#servicios-home');
            exit;
        }

        if (!hero_admin_link_is_valid($serviceLink)) {
            flash_set('err', 'El link del bloque no es valido. Podes usar https://..., una ruta como /alquileres o un ancla como #categorias.');
            header('Location: ' . ADMIN_URL . '/home.php' . ($blockId > 0 ? '?edit_service=' . $blockId : '') . '#servicios-home');
            exit;
        }

        $existingBlock = null;
        if ($blockId > 0) {
            $stmt = $db->prepare("SELECT * FROM home_service_blocks WHERE id = ?");
            $stmt->execute([$blockId]);
            $existingBlock = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $serviceImagePath = trim((string)($existingBlock['image_path'] ?? ''));

        if (isset($_FILES['service_image']) && (int)($_FILES['service_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)($_FILES['service_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['service_image']['tmp_name'])) {
                $validationError = null;
                if (!image_validate_upload($_FILES['service_image']['tmp_name'], (int)$_FILES['service_image']['size'], $validationError)) {
                    flash_set('err', 'La imagen del bloque no es valida: ' . $validationError . '.');
                    header('Location: ' . ADMIN_URL . '/home.php' . ($blockId > 0 ? '?edit_service=' . $blockId : '') . '#servicios-home');
                    exit;
                }

                $processedPath = image_process_home_service($_FILES['service_image']['tmp_name']);
                if ($processedPath === false) {
                    flash_set('err', 'No se pudo procesar la imagen del bloque.');
                    header('Location: ' . ADMIN_URL . '/home.php' . ($blockId > 0 ? '?edit_service=' . $blockId : '') . '#servicios-home');
                    exit;
                }

                if ($serviceImagePath !== '' && hero_admin_is_managed_upload($serviceImagePath)) {
                    $oldFile = ROOT_PATH . '/' . ltrim($serviceImagePath, '/');
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                $serviceImagePath = $processedPath;
            } else {
                flash_set('err', 'No se pudo subir la imagen del bloque.');
                header('Location: ' . ADMIN_URL . '/home.php' . ($blockId > 0 ? '?edit_service=' . $blockId : '') . '#servicios-home');
                exit;
            }
        }

        if ($serviceImagePath === '') {
            flash_set('err', 'La imagen del bloque es obligatoria.');
            header('Location: ' . ADMIN_URL . '/home.php' . ($blockId > 0 ? '?edit_service=' . $blockId : '') . '#servicios-home');
            exit;
        }

        if ($existingBlock) {
            $stmt = $db->prepare("
                UPDATE home_service_blocks
                SET image_path = ?, link_url = ?, title = ?, description = ?, style_key = ?,
                    position = ?, is_active = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([
                $serviceImagePath, $serviceLink, $serviceTitle, $serviceDescription, $styleKey,
                $position, $isActive, $blockId
            ]);
            flash_set('ok', 'Bloque actualizado.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO home_service_blocks (
                    image_path, link_url, title, description, style_key, position, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ");
            $stmt->execute([
                $serviceImagePath, $serviceLink, $serviceTitle, $serviceDescription, $styleKey,
                $position, $isActive
            ]);
            flash_set('ok', 'Bloque creado.');
        }

        header('Location: ' . ADMIN_URL . '/home.php#servicios-home');
        exit;
    }

    if ($action === 'delete') {
        $slideId = max(0, (int)($_POST['slide_id'] ?? 0));
        if ($slideId <= 0) {
            flash_set('err', 'No se encontro el slide a eliminar.');
            header('Location: ' . ADMIN_URL . '/home.php');
            exit;
        }

        $stmt = $db->prepare("SELECT image_path FROM home_hero_slides WHERE id = ?");
        $stmt->execute([$slideId]);
        $slide = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($slide) {
            $db->prepare("DELETE FROM home_hero_slides WHERE id = ?")->execute([$slideId]);
            $imagePath = trim((string)($slide['image_path'] ?? ''));
            if ($imagePath !== '' && hero_admin_is_managed_upload($imagePath)) {
                $oldFile = ROOT_PATH . '/' . ltrim($imagePath, '/');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            flash_set('ok', 'Slide eliminado.');
        } else {
            flash_set('err', 'No se encontro el slide a eliminar.');
        }

        header('Location: ' . ADMIN_URL . '/home.php');
        exit;
    }

    $slideId = max(0, (int)($_POST['slide_id'] ?? 0));
    $heroTag = trim((string)($_POST['hero_tag'] ?? ''));
    $heroTitle = trim((string)($_POST['hero_title'] ?? ''));
    $heroDescription = trim((string)($_POST['hero_description'] ?? ''));
    $heroLink = trim((string)($_POST['hero_link'] ?? ''));
    $heroButtonPrimaryText = trim((string)($_POST['hero_button_primary_text'] ?? ''));
    $heroButtonPrimaryLink = trim((string)($_POST['hero_button_primary_link'] ?? ''));
    $heroButtonSecondaryText = trim((string)($_POST['hero_button_secondary_text'] ?? ''));
    $heroButtonSecondaryLink = trim((string)($_POST['hero_button_secondary_link'] ?? ''));
    $position = max(0, (int)($_POST['position'] ?? 0));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($heroTitle === '') {
        flash_set('err', 'El titulo del slide es obligatorio.');
        header('Location: ' . ADMIN_URL . '/home.php' . ($slideId > 0 ? '?edit=' . $slideId : ''));
        exit;
    }

    foreach ([
        'link general del slide' => $heroLink,
        'link del boton principal' => $heroButtonPrimaryLink,
        'link del boton secundario' => $heroButtonSecondaryLink,
    ] as $label => $value) {
        if (!hero_admin_link_is_valid($value)) {
            flash_set('err', 'El ' . $label . ' no es valido. Podes usar https://..., una ruta como /catalogo o un ancla como #categorias.');
            header('Location: ' . ADMIN_URL . '/home.php' . ($slideId > 0 ? '?edit=' . $slideId : ''));
            exit;
        }
    }

    $existingSlide = null;
    if ($slideId > 0) {
        $stmt = $db->prepare("SELECT * FROM home_hero_slides WHERE id = ?");
        $stmt->execute([$slideId]);
        $existingSlide = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $heroImagePath = trim((string)($existingSlide['image_path'] ?? ''));

    if (isset($_FILES['hero_image']) && (int)($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int)($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['hero_image']['tmp_name'])) {
            $validationError = null;
            if (!image_validate_upload($_FILES['hero_image']['tmp_name'], (int)$_FILES['hero_image']['size'], $validationError)) {
                flash_set('err', 'La imagen del slide no es valida: ' . $validationError . '.');
                header('Location: ' . ADMIN_URL . '/home.php' . ($slideId > 0 ? '?edit=' . $slideId : ''));
                exit;
            }

            $processedPath = image_process_home_hero($_FILES['hero_image']['tmp_name']);
            if ($processedPath === false) {
                flash_set('err', 'No se pudo procesar la imagen del slide.');
                header('Location: ' . ADMIN_URL . '/home.php' . ($slideId > 0 ? '?edit=' . $slideId : ''));
                exit;
            }

            if ($heroImagePath !== '' && hero_admin_is_managed_upload($heroImagePath)) {
                $oldFile = ROOT_PATH . '/' . ltrim($heroImagePath, '/');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $heroImagePath = $processedPath;
        } else {
            flash_set('err', 'No se pudo subir la imagen del slide.');
            header('Location: ' . ADMIN_URL . '/home.php' . ($slideId > 0 ? '?edit=' . $slideId : ''));
            exit;
        }
    }

    if ($heroImagePath === '') {
        flash_set('err', 'La imagen del slide es obligatoria.');
        header('Location: ' . ADMIN_URL . '/home.php' . ($slideId > 0 ? '?edit=' . $slideId : ''));
        exit;
    }

    if ($existingSlide) {
        $stmt = $db->prepare("
            UPDATE home_hero_slides
            SET image_path = ?, link_url = ?, tag = ?, title = ?, description = ?,
                button_primary_text = ?, button_primary_link = ?,
                button_secondary_text = ?, button_secondary_link = ?,
                position = ?, is_active = ?, updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([
            $heroImagePath, $heroLink, $heroTag, $heroTitle, $heroDescription,
            $heroButtonPrimaryText, $heroButtonPrimaryLink,
            $heroButtonSecondaryText, $heroButtonSecondaryLink,
            $position, $isActive, $slideId
        ]);
        flash_set('ok', 'Slide actualizado.');
    } else {
        $stmt = $db->prepare("
            INSERT INTO home_hero_slides (
                image_path, link_url, tag, title, description,
                button_primary_text, button_primary_link,
                button_secondary_text, button_secondary_link,
                position, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            $heroImagePath, $heroLink, $heroTag, $heroTitle, $heroDescription,
            $heroButtonPrimaryText, $heroButtonPrimaryLink,
            $heroButtonSecondaryText, $heroButtonSecondaryLink,
            $position, $isActive
        ]);
        flash_set('ok', 'Slide creado.');
    }

    header('Location: ' . ADMIN_URL . '/home.php');
    exit;
}

hero_admin_ensure_default_slide($db);
$slides = hero_admin_fetch_slides($db);
$serviceBlocks = service_admin_fetch_blocks($db);
$editId = max(0, (int)($_GET['edit'] ?? 0));
$editServiceId = max(0, (int)($_GET['edit_service'] ?? 0));
$editingSlide = null;
$editingService = null;

if ($editId > 0) {
    foreach ($slides as $slide) {
        if ((int)$slide['id'] === $editId) {
            $editingSlide = $slide;
            break;
        }
    }
}

if ($editServiceId > 0) {
    foreach ($serviceBlocks as $block) {
        if ((int)$block['id'] === $editServiceId) {
            $editingService = $block;
            break;
        }
    }
}

$formData = [
    'id' => (int)($editingSlide['id'] ?? 0),
    'image_path' => trim((string)($editingSlide['image_path'] ?? '')),
    'link_url' => trim((string)($editingSlide['link_url'] ?? '')),
    'tag' => trim((string)($editingSlide['tag'] ?? 'Casa Montepio - CABA')),
    'title' => trim((string)($editingSlide['title'] ?? '')),
    'description' => trim((string)($editingSlide['description'] ?? '')),
    'button_primary_text' => trim((string)($editingSlide['button_primary_text'] ?? 'Ver catalogo')),
    'button_primary_link' => trim((string)($editingSlide['button_primary_link'] ?? '#categorias')),
    'button_secondary_text' => trim((string)($editingSlide['button_secondary_text'] ?? 'Como llegar')),
    'button_secondary_link' => trim((string)($editingSlide['button_secondary_link'] ?? '#ubicacion')),
    'position' => (int)($editingSlide['position'] ?? count($slides)),
    'is_active' => (int)($editingSlide['is_active'] ?? 1) === 1,
];

$heroImageUrl = $formData['image_path'] !== '' ? BASE_URL . '/' . ltrim($formData['image_path'], '/') : '';
$contactSettings = contact_admin_settings($db);
$serviceFormData = [
    'id' => (int)($editingService['id'] ?? 0),
    'image_path' => trim((string)($editingService['image_path'] ?? '')),
    'link_url' => trim((string)($editingService['link_url'] ?? '')),
    'title' => trim((string)($editingService['title'] ?? '')),
    'description' => trim((string)($editingService['description'] ?? '')),
    'style_key' => trim((string)($editingService['style_key'] ?? 'default')),
    'position' => (int)($editingService['position'] ?? count($serviceBlocks)),
    'is_active' => (int)($editingService['is_active'] ?? 1) === 1,
];
$serviceImageUrl = $serviceFormData['image_path'] !== '' ? BASE_URL . '/' . ltrim($serviceFormData['image_path'], '/') : '';
$serviceStyleOptions = service_admin_style_options();
$topbarActions = [
    ['href' => BASE_URL . '/', 'label' => 'Ver home', 'class' => 'btn btn-outline btn-sm'],
    ['href' => ADMIN_URL . '/home.php#slides-home', 'label' => 'Slides', 'class' => 'btn btn-outline btn-sm'],
    ['href' => ADMIN_URL . '/home.php#servicios-home', 'label' => 'Bloques', 'class' => 'btn btn-outline btn-sm'],
    ['href' => ADMIN_URL . '/home.php#visitanos-home', 'label' => 'Visitanos', 'class' => 'btn btn-outline btn-sm'],
];

if ($editingSlide) {
    $topbarActions[] = ['href' => ADMIN_URL . '/home.php', 'label' => 'Nuevo slide', 'class' => 'btn btn-primary btn-sm'];
}

if ($editingService) {
    $topbarActions[] = ['href' => ADMIN_URL . '/home.php#servicios-home', 'label' => 'Nuevo bloque', 'class' => 'btn btn-primary btn-sm'];
}

layout_head('Home');
layout_sidebar('home.php');
?>
<div class="main">
<?php layout_topbar('Home', $topbarActions); ?>
<div class="content">
<?php layout_flash(); ?>

<div class="home-admin-nav">
  <a href="#slides-home" class="home-admin-nav-link">Slides del hero</a>
  <a href="#servicios-home" class="home-admin-nav-link">Bloques de servicios</a>
  <a href="#visitanos-home" class="home-admin-nav-link">Visitanos</a>
</div>

<section class="home-admin-section" id="slides-home">
  <div class="home-admin-section-head">
    <div>
      <span class="section-kicker">Home</span>
      <h2>Slides del hero</h2>
      <p>Administracion del carrusel principal: imagen, textos, links, botones y orden.</p>
    </div>
    <?php if ($editingSlide): ?>
      <a href="<?= ADMIN_URL ?>/home.php#slides-home" class="btn btn-primary btn-sm">Nuevo slide</a>
    <?php endif; ?>
  </div>

<div class="home-admin-grid">
  <div class="card">
    <div class="card-header">
      <h3><?= $editingSlide ? 'Editar slide' : 'Nuevo slide' ?></h3>
      <span class="text-sm text-m">Cada slide tiene su imagen, textos y botones</span>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" class="form-grid">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="slide_id" value="<?= $formData['id'] ?>">

        <?php if ($heroImageUrl !== ''): ?>
        <div class="form-group form-full">
          <label>Imagen actual</label>
          <div class="hero-admin-preview">
            <img src="<?= h($heroImageUrl) ?>" alt="Vista previa del slide">
          </div>
        </div>
        <?php endif; ?>

        <div class="form-group form-full">
          <label for="hero_image">Imagen del slide</label>
          <input type="file" id="hero_image" name="hero_image" accept="image/*">
          <span class="form-hint">Recomendado: formato horizontal. <?= $editingSlide ? 'Si no subis una nueva, se mantiene la actual.' : 'Es obligatoria para crear el slide.' ?></span>
        </div>

        <div class="form-group form-full">
          <label for="hero_link">Link general del slide</label>
          <input type="text" id="hero_link" name="hero_link" value="<?= h($formData['link_url']) ?>" placeholder="https://...">
          <span class="form-hint">Opcional. Puede ser `https://...`, `/catalogo` o `#categorias`.</span>
        </div>

        <div class="form-group">
          <label for="hero_tag">Etiqueta superior</label>
          <input type="text" id="hero_tag" name="hero_tag" value="<?= h($formData['tag']) ?>" placeholder="Casa Montepio · CABA">
        </div>

        <div class="form-group">
          <label for="hero_title">Titulo</label>
          <input type="text" id="hero_title" name="hero_title" value="<?= h($formData['title']) ?>" placeholder="Antiguedades con historia y estilo">
        </div>

        <div class="form-group form-full">
          <label for="hero_description">Bajada</label>
          <textarea id="hero_description" name="hero_description" rows="4" placeholder="Texto descriptivo del slide"><?= h($formData['description']) ?></textarea>
        </div>

        <div class="form-group">
          <label for="hero_button_primary_text">Boton principal: texto</label>
          <input type="text" id="hero_button_primary_text" name="hero_button_primary_text" value="<?= h($formData['button_primary_text']) ?>" placeholder="Ver catalogo">
        </div>

        <div class="form-group">
          <label for="hero_button_primary_link">Boton principal: link</label>
          <input type="text" id="hero_button_primary_link" name="hero_button_primary_link" value="<?= h($formData['button_primary_link']) ?>" placeholder="#categorias">
        </div>

        <div class="form-group">
          <label for="hero_button_secondary_text">Boton secundario: texto</label>
          <input type="text" id="hero_button_secondary_text" name="hero_button_secondary_text" value="<?= h($formData['button_secondary_text']) ?>" placeholder="Como llegar">
        </div>

        <div class="form-group">
          <label for="hero_button_secondary_link">Boton secundario: link</label>
          <input type="text" id="hero_button_secondary_link" name="hero_button_secondary_link" value="<?= h($formData['button_secondary_link']) ?>" placeholder="#ubicacion">
        </div>

        <div class="form-group">
          <label for="position">Orden</label>
          <input type="number" id="position" name="position" min="0" value="<?= $formData['position'] ?>">
        </div>

        <div class="form-group">
          <label>Estado</label>
          <label class="checkbox-line">
            <input type="checkbox" name="is_active" value="1" <?= $formData['is_active'] ? 'checked' : '' ?>>
            <span>Slide activo</span>
          </label>
        </div>

        <div class="form-group form-full">
          <button type="submit" class="btn btn-primary"><?= $editingSlide ? 'Guardar cambios' : 'Agregar slide' ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Slides cargados</h3>
      <span class="text-sm text-m"><?= count($slides) ?> total</span>
    </div>
    <div class="card-body">
      <?php if (empty($slides)): ?>
        <div class="text-sm text-m">Todavia no hay slides cargados.</div>
      <?php else: ?>
        <div class="slides-admin-list">
          <?php foreach ($slides as $slide): ?>
            <article class="slide-admin-item">
              <div class="slide-admin-thumb">
                <img src="<?= h(BASE_URL . '/' . ltrim((string)$slide['image_path'], '/')) ?>" alt="">
              </div>
              <div class="slide-admin-copy">
                <div class="slide-admin-meta">
                  <strong><?= h((string)$slide['title']) ?></strong>
                  <span class="badge <?= (int)$slide['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>"><?= (int)$slide['is_active'] === 1 ? 'Activo' : 'Oculto' ?></span>
                </div>
                <div class="text-sm text-m">Orden <?= (int)$slide['position'] ?></div>
                <?php if (trim((string)$slide['tag']) !== ''): ?>
                  <div class="text-sm"><?= h((string)$slide['tag']) ?></div>
                <?php endif; ?>
              </div>
              <div class="slide-admin-actions">
                <a href="<?= ADMIN_URL . '/home.php?edit=' . (int)$slide['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
                <form method="POST" onsubmit="return confirm('¿Eliminar este slide?')">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="slide_id" value="<?= (int)$slide['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</section>

<section class="home-admin-section" id="servicios-home">
  <div class="home-admin-section-head">
    <div>
      <span class="section-kicker">Home</span>
      <h2>Bloques de servicios</h2>
      <p>Administracion de las cards debajo del hero, como Alquileres, Compra y venta y Restauraciones.</p>
    </div>
    <?php if ($editingService): ?>
      <a href="<?= ADMIN_URL ?>/home.php#servicios-home" class="btn btn-primary btn-sm">Nuevo bloque</a>
    <?php endif; ?>
  </div>

<div class="home-admin-grid services-admin-section">
  <div class="card">
    <div class="card-header">
      <h3><?= $editingService ? 'Editar bloque de servicios' : 'Nuevo bloque de servicios' ?></h3>
      <span class="text-sm text-m">Cards que aparecen debajo del hero</span>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" class="form-grid">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save_service">
        <input type="hidden" name="block_id" value="<?= $serviceFormData['id'] ?>">

        <?php if ($serviceImageUrl !== ''): ?>
        <div class="form-group form-full">
          <label>Imagen actual</label>
          <div class="service-admin-preview">
            <img src="<?= h($serviceImageUrl) ?>" alt="Vista previa del bloque">
          </div>
        </div>
        <?php endif; ?>

        <div class="form-group form-full">
          <label for="service_image">Imagen del bloque</label>
          <input type="file" id="service_image" name="service_image" accept="image/*">
          <span class="form-hint">Recomendado: imagen horizontal. <?= $editingService ? 'Si no subis una nueva, se mantiene la actual.' : 'Es obligatoria para crear el bloque.' ?></span>
        </div>

        <div class="form-group">
          <label for="service_title">Titulo</label>
          <input type="text" id="service_title" name="service_title" value="<?= h($serviceFormData['title']) ?>" placeholder="Alquileres">
        </div>

        <div class="form-group">
          <label for="service_link">Link del bloque</label>
          <input type="text" id="service_link" name="service_link" value="<?= h($serviceFormData['link_url']) ?>" placeholder="/alquileres">
          <span class="form-hint">Puede ser /alquileres, /compra-venta, /restauraciones o una URL completa.</span>
        </div>

        <div class="form-group">
          <label for="style_key">Estilo visual</label>
          <select id="style_key" name="style_key">
            <?php foreach ($serviceStyleOptions as $key => $label): ?>
              <option value="<?= h($key) ?>" <?= $serviceFormData['style_key'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group form-full">
          <label for="service_description">Texto</label>
          <textarea id="service_description" name="service_description" rows="4" placeholder="Descripcion breve del bloque"><?= h($serviceFormData['description']) ?></textarea>
        </div>

        <div class="form-group">
          <label for="service_position">Orden</label>
          <input type="number" id="service_position" name="service_position" min="0" value="<?= $serviceFormData['position'] ?>">
        </div>

        <div class="form-group">
          <label>Estado</label>
          <label class="checkbox-line">
            <input type="checkbox" name="service_is_active" value="1" <?= $serviceFormData['is_active'] ? 'checked' : '' ?>>
            <span>Bloque activo</span>
          </label>
        </div>

        <div class="form-group form-full">
          <button type="submit" class="btn btn-primary"><?= $editingService ? 'Guardar bloque' : 'Agregar bloque' ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Bloques cargados</h3>
      <span class="text-sm text-m"><?= count($serviceBlocks) ?> total</span>
    </div>
    <div class="card-body">
      <?php if (empty($serviceBlocks)): ?>
        <div class="text-sm text-m">Todavia no hay bloques cargados.</div>
      <?php else: ?>
        <div class="slides-admin-list">
          <?php foreach ($serviceBlocks as $block): ?>
            <article class="slide-admin-item">
              <div class="slide-admin-thumb">
                <img src="<?= h(BASE_URL . '/' . ltrim((string)$block['image_path'], '/')) ?>" alt="">
              </div>
              <div class="slide-admin-copy">
                <div class="slide-admin-meta">
                  <strong><?= h((string)$block['title']) ?></strong>
                  <span class="badge <?= (int)$block['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>"><?= (int)$block['is_active'] === 1 ? 'Activo' : 'Oculto' ?></span>
                </div>
                <div class="text-sm text-m">Orden <?= (int)$block['position'] ?> - <?= h($serviceStyleOptions[(string)$block['style_key']] ?? 'Verde neutro') ?></div>
                <?php if (trim((string)($block['link_url'] ?? '')) !== ''): ?>
                  <div class="text-sm text-m">Link <?= h((string)$block['link_url']) ?></div>
                <?php endif; ?>
                <?php if (trim((string)$block['description']) !== ''): ?>
                  <div class="text-sm"><?= h((string)$block['description']) ?></div>
                <?php endif; ?>
              </div>
              <div class="slide-admin-actions">
                <a href="<?= ADMIN_URL . '/home.php?edit_service=' . (int)$block['id'] . '#servicios-home' ?>" class="btn btn-outline btn-sm">Editar</a>
                <form method="POST" onsubmit="return confirm('Eliminar este bloque?')">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete_service">
                  <input type="hidden" name="block_id" value="<?= (int)$block['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

</section>

<section class="home-admin-section" id="visitanos-home">
  <div class="home-admin-section-head">
    <div>
      <span class="section-kicker">Home</span>
      <h2>Visitanos</h2>
      <p>Edicion del bloque de contacto que aparece junto al mapa: titulo, valores visibles y links.</p>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Datos del bloque</h3>
      <span class="text-sm text-m">Titulo, valor y link por item</span>
    </div>
    <div class="card-body">
      <form method="POST" class="form-grid">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="save_contact">

        <div class="form-group form-full">
          <label for="contact_title">Titulo general</label>
          <input type="text" id="contact_title" name="contact_title" value="<?= h($contactSettings['title']) ?>" placeholder="Visitanos">
        </div>

        <?php foreach ($contactSettings['items'] as $key => $item): ?>
          <div class="contact-admin-item form-full">
            <div class="contact-admin-item-head">
              <strong><?= h($item['label']) ?></strong>
              <span class="text-sm text-m"><?= h($key) ?></span>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label for="contact_<?= h($key) ?>_label">Titulo</label>
                <input type="text" id="contact_<?= h($key) ?>_label" name="contact_<?= h($key) ?>_label" value="<?= h($item['label']) ?>">
              </div>
              <div class="form-group">
                <label for="contact_<?= h($key) ?>_link">Link</label>
                <input type="text" id="contact_<?= h($key) ?>_link" name="contact_<?= h($key) ?>_link" value="<?= h($item['link']) ?>" placeholder="https://..., tel:..., mailto:...">
              </div>
              <div class="form-group form-full">
                <label for="contact_<?= h($key) ?>_value">Valor visible</label>
                <textarea id="contact_<?= h($key) ?>_value" name="contact_<?= h($key) ?>_value" rows="2"><?= h($item['value']) ?></textarea>
                <span class="form-hint">Podes usar saltos de linea. El link es opcional.</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="form-group form-full">
          <button type="submit" class="btn btn-primary">Guardar Visitanos</button>
        </div>
      </form>
    </div>
  </div>
</section>

</div>
</div>

<style>
.home-admin-nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
.home-admin-nav-link{display:inline-flex;align-items:center;padding:8px 14px;border:1px solid #d8d2c9;border-radius:999px;background:white;color:var(--text);font-size:13px;font-weight:600;text-decoration:none}
.home-admin-nav-link:hover{background:var(--gray-l)}
.home-admin-section{scroll-margin-top:84px;margin-bottom:34px}
.home-admin-section + .home-admin-section{padding-top:28px;border-top:1px solid #ece7dd}
.home-admin-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:16px}
.home-admin-section-head h2{font-size:22px;line-height:1.2;color:var(--text);margin:3px 0 5px}
.home-admin-section-head p{font-size:13px;color:var(--gray-m);max-width:680px}
.section-kicker{font-size:10px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--gold)}
.home-admin-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:20px;align-items:start}
.hero-admin-preview{overflow:hidden;border:1px solid #ece7dd;border-radius:14px;background:var(--gray-l)}
.hero-admin-preview img{display:block;width:100%;aspect-ratio:16/7;object-fit:cover}
.services-admin-section{margin-top:0}
.service-admin-preview{overflow:hidden;border:1px solid #ece7dd;border-radius:14px;background:var(--gray-l)}
.service-admin-preview img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover}
.contact-admin-item{padding:16px;border:1px solid #ece7dd;border-radius:12px;background:#fff}
.contact-admin-item + .contact-admin-item{margin-top:2px}
.contact-admin-item-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.contact-admin-item-head strong{font-size:14px;color:var(--text)}
.checkbox-line{display:flex;align-items:center;gap:10px;font-weight:400}
.checkbox-line input{width:auto}
.slides-admin-list{display:flex;flex-direction:column;gap:14px}
.slide-admin-item{display:grid;grid-template-columns:110px minmax(0,1fr) auto;gap:14px;align-items:center;padding:12px;border:1px solid #ece7dd;border-radius:12px}
.slide-admin-thumb{overflow:hidden;border-radius:10px;background:var(--gray-l)}
.slide-admin-thumb img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover}
.slide-admin-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
.slide-admin-copy{min-width:0}
.slide-admin-actions{display:flex;gap:8px;align-items:center}
.slide-admin-actions form{margin:0}
@media(max-width:1100px){.home-admin-grid{grid-template-columns:1fr}.slide-admin-item{grid-template-columns:90px minmax(0,1fr)}.slide-admin-actions{grid-column:1/-1}}
</style>
<?php layout_foot(); ?>
