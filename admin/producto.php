<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

auth_require();

$db = db();
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$p = null;
$images = [];
if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) {
        header('Location: ' . ADMIN_URL . '/dashboard.php');
        exit;
    }

    $imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, position");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}

$categories = $db->query("SELECT * FROM categories ORDER BY parent_id NULLS FIRST, position, name")->fetchAll();
$pageTitle = $isEdit ? 'Editar: ' . ($p['title'] ?? '') : 'Nuevo producto';

$selectedCatIds = [];
if ($isEdit) {
    $catStmt = $db->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
    $catStmt->execute([$id]);
    $selectedCatIds = $catStmt->fetchAll(PDO::FETCH_COLUMN);
}

$catParents = array_filter($categories, fn($c) => $c['parent_id'] === null);
$catChildren = [];
foreach ($categories as $c) {
    if ($c['parent_id'] !== null) {
        $catChildren[$c['parent_id']][] = $c;
    }
}

$statusList = [
    'activo' => 'Activo',
    'reservado' => 'Reservado',
    'vendido' => 'Vendido',
];

$rentalOnly = (int)($p['rental_only'] ?? 0) === 1;

layout_head($pageTitle, '<style>
.form-section{margin-bottom:28px;}
.form-section-title{font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #ece7dd;}
.category-picker{border:1px solid #d8d2c9;border-radius:8px;padding:10px 12px;max-height:260px;overflow-y:auto;background:var(--gray-l);}
.category-group{border-bottom:1px solid #ece7dd;padding:6px 0;}
.category-group:last-child{border-bottom:none;padding-bottom:0;}
.category-parent-row{display:flex;align-items:center;gap:10px;}
.category-parent-toggle{padding:0;border:none;background:transparent;color:var(--gray-d);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:color .2s ease;}
.category-parent-toggle:hover{color:var(--green);}
.category-parent-toggle[aria-expanded="true"]{color:var(--green);}
.category-parent-toggle[aria-expanded="true"] svg{transform:rotate(90deg);}
.category-parent-toggle svg{transition:transform .2s ease;}
.category-parent-label{display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;flex:1;}
.category-children{display:none;padding:8px 0 4px 42px;}
.category-children.is-open{display:block;}
.category-child-label{display:flex;align-items:center;gap:8px;cursor:pointer;padding:5px 0 5px 10px;color:var(--gray-d);}
</style>');
layout_sidebar($isEdit ? '' : 'producto.php');
?>
<div class="main">
<?php layout_topbar($pageTitle, [
    ['href' => ADMIN_URL . '/dashboard.php', 'label' => '<- Volver', 'class' => 'btn btn-outline btn-sm'],
]); ?>
<div class="content">
<?php layout_flash(); ?>

<form method="POST" action="guardar.php" enctype="multipart/form-data" id="productForm">
  <?= csrf_input() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
  <input type="hidden" name="type" id="typeField" value="<?= $rentalOnly ? 'alquiler' : (($p['type'] ?? 'venta') === 'alquiler' ? 'alquiler' : ($p['type'] ?? 'venta')) ?>">

  <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">
    <div>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h3>Identificacion</h3></div>
        <div class="card-body">
          <div class="form-section">
            <div class="form-grid">
              <div class="form-group form-full">
                <label>Nombre del producto <span class="req">*</span></label>
                <input type="text" name="title" value="<?= h($p['title'] ?? '') ?>" required placeholder="Ej: Aparador de roble con marmol">
                <span class="form-hint">Es el nombre principal que se muestra en el sitio.</span>
              </div>
              <div class="form-group form-full">
                <label>Categorias</label>
                <div class="category-picker">
                  <?php if (empty($catParents)): ?>
                    <span class="form-hint">No hay categorias. <a href="categorias.php">Crear categorias</a></span>
                  <?php else: ?>
                    <?php foreach ($catParents as $parent): ?>
                      <?php
                        $children = $catChildren[$parent['id']] ?? [];
                        $hasSelectedChild = false;
                        foreach ($children as $childCheck) {
                            if (in_array($childCheck['id'], $selectedCatIds)) {
                                $hasSelectedChild = true;
                                break;
                            }
                        }
                        $isExpanded = $hasSelectedChild;
                      ?>
                      <div class="category-group">
                        <div class="category-parent-row">
                          <?php if (!empty($children)): ?>
                            <button type="button"
                                    class="category-parent-toggle"
                                    data-category-toggle
                                    aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                                    aria-controls="category-children-<?= $parent['id'] ?>">
                              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></svg>
                            </button>
                          <?php else: ?>
                            <span style="width:12px;display:inline-block;flex-shrink:0;"></span>
                          <?php endif; ?>
                          <label class="category-parent-label">
                            <input type="checkbox" name="category_ids[]" value="<?= $parent['id'] ?>" <?= in_array($parent['id'], $selectedCatIds) ? 'checked' : '' ?> style="width:auto;margin:0;accent-color:var(--green);">
                            <?= h($parent['name']) ?> <span style="color:var(--gray-m);font-weight:400;">(categoria principal)</span>
                          </label>
                        </div>
                        <?php if (!empty($children)): ?>
                          <div id="category-children-<?= $parent['id'] ?>" class="category-children <?= $isExpanded ? 'is-open' : '' ?>">
                            <?php foreach ($children as $child): ?>
                              <label class="category-child-label">
                                <input type="checkbox" name="category_ids[]" value="<?= $child['id'] ?>" <?= in_array($child['id'], $selectedCatIds) ? 'checked' : '' ?> style="width:auto;margin:0;accent-color:var(--green);">
                                <?= h($child['name']) ?> <span style="color:var(--gray-m);">(subcategoria)</span>
                              </label>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <span class="form-hint">Podes seleccionar varias categorias y subcategorias.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h3>Descripcion</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label>Descripcion corta</label>
            <textarea name="description" rows="5" placeholder="Detalle principal visible en la ficha del producto..."><?= h($p['description'] ?? '') ?></textarea>
            <span class="form-hint">Si la dejas vacia, el sitio no muestra un bloque incompleto.</span>
          </div>
          <input type="hidden" name="history" value="<?= h($p['history'] ?? '') ?>">
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h3>Ficha tecnica</h3></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label>Estilo</label>
              <input type="text" name="style" value="<?= h($p['style'] ?? '') ?>" placeholder="Ej: Victoriano, Art Deco, Mid Century">
            </div>
            <div class="form-group">
              <label>Epoca / Ano aprox.</label>
              <input type="text" name="era" value="<?= h($p['era'] ?? '') ?>" placeholder="Ej: Ca. 1890, Decada del 50">
            </div>
            <div class="form-group">
              <label>Material</label>
              <input type="text" name="material" value="<?= h($p['material'] ?? '') ?>" placeholder="Ej: Roble, bronce, cuero">
            </div>
            <div class="form-group">
              <label>Origen</label>
              <input type="text" name="origin" value="<?= h($p['origin'] ?? '') ?>" placeholder="Ej: Europa, Argentina">
            </div>
            <div class="form-group">
              <label>Dimensiones</label>
              <input type="text" name="dimensions" value="<?= h($p['dimensions'] ?? '') ?>" placeholder="Ej: 1,50 x 0,50 x 1,00 alto">
            </div>
            <div class="form-group">
              <label>Estado</label>
              <select name="condition_val">
                <option value="">-</option>
                <?php foreach (['Excelente', 'Muy bueno', 'Bueno', 'Para restaurar'] as $conditionOption): ?>
                  <option value="<?= $conditionOption ?>" <?= ($p['condition_val'] ?? '') === $conditionOption ? 'selected' : '' ?>><?= $conditionOption ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <p class="form-hint" style="margin-top:12px;">El boton y el bloque de ficha tecnica solo aparecen en el sitio si completas algun dato.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Imagenes</h3>
          <span class="text-sm text-m">JPG, PNG, WEBP · Max <?= MAX_UPLOAD_MB ?>MB · hasta <?= MAX_IMAGE_WIDTH ?>x<?= MAX_IMAGE_HEIGHT ?> px</span>
        </div>
        <div class="card-body">
          <?php if (!empty($images)): ?>
            <div class="img-previews" id="existingImgs">
              <?php foreach ($images as $img): ?>
                <div class="img-preview-item" id="img-<?= (int)$img['id'] ?>">
                  <img src="/Montepio/<?= h($img['path_thumb']) ?>" alt="">
                  <?php if ($img['is_cover']): ?>
                    <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,77,51,.7);color:white;font-size:9px;text-align:center;padding:2px;">portada</div>
                  <?php endif; ?>
                  <button type="button" class="del-btn" onclick="submitDeleteImage(<?= (int)$img['id'] ?>)">x</button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="sep"></div>
          <?php endif; ?>

          <label>Agregar imagenes</label>
          <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" id="imgInput" style="margin-top:6px;">
          <div class="img-previews" id="newImgPreviews"></div>
          <p class="form-hint" style="margin-top:8px;">La primera imagen valida se usa como portada si todavia no hay una definida.</p>
        </div>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3>Modalidad</h3></div>
        <div class="card-body">
          <div class="form-group" style="margin-bottom:14px;">
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
              <input type="checkbox" name="rental_only" id="rentalOnly" value="1" <?= $rentalOnly ? 'checked' : '' ?> style="width:auto;margin-top:3px;">
              <span>
                <strong>Solo alquiler</strong><br>
                <span class="form-hint">Si esta marcado, el producto se muestra solo para alquiler. Si no, queda como venta y puede llevar precio.</span>
              </span>
            </label>
          </div>

          <div id="priceBlock" style="<?= $rentalOnly ? 'display:none' : '' ?>">
            <div class="form-group" style="margin-bottom:10px;">
              <label>Precio de venta ($)</label>
              <input type="number" name="price" value="<?= h((string)($p['price'] ?? '')) ?>" min="0" step="1000" placeholder="Dejar vacio = Consultar">
            </div>
            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="price_visible" value="1" <?= ($p['price_visible'] ?? 1) ? 'checked' : '' ?> style="width:auto;margin:0;">
                Mostrar precio de venta en el sitio
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3>Entrega</h3></div>
        <div class="card-body">
          <div class="form-group" style="margin-bottom:10px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" name="pickup_available" value="1" <?= !empty($p['pickup_available']) ? 'checked' : '' ?> style="width:auto;margin:0;">
              Retiro en local
            </label>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" name="shipping_transport" value="1" <?= !empty($p['shipping_transport']) ? 'checked' : '' ?> style="width:auto;margin:0;">
              Envio por transporte
            </label>
          </div>
          <div class="form-group" style="margin-bottom:10px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" name="shipping_flete" value="1" <?= !empty($p['shipping_flete']) ? 'checked' : '' ?> style="width:auto;margin:0;">
              Envio con flete
            </label>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" name="shipping_encomienda" value="1" <?= !empty($p['shipping_encomienda']) ? 'checked' : '' ?> style="width:auto;margin:0;">
              Envio por encomienda
            </label>
          </div>
          <p class="form-hint" style="margin-top:12px;">En el sitio solo se muestran las opciones que marques. Si no marcas ninguna, no se muestra el bloque.</p>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h3>Estado</h3></div>
        <div class="card-body">
          <div class="form-group" style="margin-bottom:14px;">
            <label>Estado de la pieza</label>
            <select name="status">
              <?php foreach ($statusList as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($p['status'] ?? 'activo') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" name="is_featured" value="1" <?= ($p['is_featured'] ?? 0) ? 'checked' : '' ?> style="width:auto;margin:0;">
              Destacar en el sitio
            </label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:14px;">
        <?= $isEdit ? 'Guardar cambios' : 'Crear producto' ?>
      </button>
      <?php if ($isEdit): ?>
        <a href="/Montepio/producto/<?= h($p['slug']) ?>" target="_blank" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:8px;">
          Ver en el sitio ->
        </a>
      <?php endif; ?>
    </div>
  </div>
</form>

<form method="POST" action="guardar.php" id="deleteImageForm" style="display:none;">
  <?= csrf_input() ?>
  <input type="hidden" name="action" value="delete_image">
  <input type="hidden" name="image_id" id="deleteImageId" value="">
  <input type="hidden" name="product_id" value="<?= $id ?>">
</form>
</div>
</div>

<script>
const rentalOnlyInput = document.getElementById('rentalOnly');
const priceBlock = document.getElementById('priceBlock');
const typeField = document.getElementById('typeField');

function syncMode() {
  const rentalOnly = rentalOnlyInput.checked;
  priceBlock.style.display = rentalOnly ? 'none' : '';
  typeField.value = rentalOnly ? 'alquiler' : 'venta';
}

rentalOnlyInput.addEventListener('change', syncMode);
syncMode();

function submitDeleteImage(imageId) {
  if (!confirm('Eliminar esta imagen?')) return;
  document.getElementById('deleteImageId').value = imageId;
  document.getElementById('deleteImageForm').submit();
}

document.getElementById('imgInput').addEventListener('change', function () {
  const wrap = document.getElementById('newImgPreviews');
  wrap.innerHTML = '';
  Array.from(this.files).forEach((file) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const div = document.createElement('div');
      div.className = 'img-preview-item';
      div.innerHTML = '<img src="' + e.target.result + '" alt="">';
      wrap.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
});

document.querySelectorAll('[data-category-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const targetId = button.getAttribute('aria-controls');
    const target = targetId ? document.getElementById(targetId) : null;
    if (!target) return;

    const expanded = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    target.classList.toggle('is-open', !expanded);
  });
});
</script>
<?php layout_foot(); ?>
