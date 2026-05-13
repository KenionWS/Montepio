<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

auth_require();

$categories = db()->query("SELECT * FROM categories ORDER BY LOWER(name), name")->fetchAll();
$catParents = array_filter($categories, fn($c) => $c['parent_id'] === null);
$catChildren = [];
foreach ($categories as $c) {
    if ($c['parent_id'] !== null) {
        $catChildren[$c['parent_id']][] = $c;
    }
}

function render_bulk_category_node(array $category, array $childrenByParent, int $level = 0): void
{
    $children = $childrenByParent[$category['id']] ?? [];
    $hasChildren = !empty($children);
    $typeLabel = $level === 0 ? 'categoria principal' : 'nivel ' . ($level + 1);
    ?>
    <div class="bulk-category-node" style="--cat-level: <?= $level ?>">
      <div class="bulk-category-row">
        <?php if ($hasChildren): ?>
          <button type="button"
                  class="bulk-category-toggle"
                  data-category-toggle
                  aria-expanded="false"
                  aria-controls="bulk-category-children-<?= (int)$category['id'] ?>">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></svg>
          </button>
        <?php else: ?>
          <span class="bulk-category-spacer"></span>
        <?php endif; ?>
        <label class="bulk-category-label <?= $level === 0 ? 'is-parent' : '' ?>">
          <input type="checkbox" class="bulk-category-checkbox" value="<?= (int)$category['id'] ?>">
          <?= h($category['name']) ?> <small><?= h($typeLabel) ?></small>
        </label>
      </div>
      <?php if ($hasChildren): ?>
        <div id="bulk-category-children-<?= (int)$category['id'] ?>" class="bulk-category-children">
          <?php foreach ($children as $child): ?>
            <?php render_bulk_category_node($child, $childrenByParent, $level + 1); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

layout_head('Carga masiva', <<<CSS
<style>
.upload-area {
  border: 2px dashed #d8d2c9;
  border-radius: 14px;
  padding: 56px 24px;
  text-align: center;
  cursor: pointer;
  transition: border-color .25s, background .25s;
  background: var(--gray-l);
  position: relative;
}
.upload-area.drag { border-color: var(--green); background: var(--green-light); }
.upload-area input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-area .ua-icon { font-size: 52px; line-height: 1; margin-bottom: 14px; }
.upload-area h2 { font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.upload-area p { font-size: 13px; color: var(--gray-m); }
.upload-area .ua-btn {
  display: inline-block;
  margin-top: 18px;
  background: var(--green);
  color: white;
  padding: 10px 24px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 600;
  pointer-events: none;
}

#globalProgress { display: none; margin: 20px 0; }
#globalProgress .gp-bar { height: 6px; background: #e0dbd2; border-radius: 3px; overflow: hidden; }
#globalProgress .gp-fill { height: 100%; background: var(--green); transition: width .3s; border-radius: 3px; width: 0%; }
#globalProgress .gp-text { font-size: 12px; color: var(--gray-m); margin-top: 6px; }

#cardsGrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 24px; }
.prod-card {
  background: white;
  border: 1px solid #ece7dd;
  border-radius: 10px;
  overflow: hidden;
  position: relative;
  transition: box-shadow .2s;
}
.prod-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.prod-card .card-thumb {
  width: 100%;
  aspect-ratio: 1;
  background: var(--gray-l);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}
.prod-card .card-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.prod-card .card-thumb .loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,.8);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
}
.prod-card .card-thumb .img-progress { width: 70%; height: 4px; background: #e0dbd2; border-radius: 2px; overflow: hidden; }
.prod-card .card-thumb .img-progress-fill { height: 100%; background: var(--green); width: 0%; transition: width .2s; border-radius: 2px; }
.prod-card .card-body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 10px; }
.prod-card .card-body input[type=text],
.prod-card .card-body select,
.prod-card .card-body input[type=number] {
  padding: 7px 10px;
  font-size: 12px;
  border-radius: 6px;
}
.prod-card .card-body .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.prod-card .remove-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: rgba(192,57,43,.85);
  color: white;
  border: none;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .2s;
}
.prod-card .remove-btn:hover { background: var(--red); }
.prod-card .card-status { position: absolute; top: 8px; left: 8px; }
.prod-destination {
  padding: 9px 10px;
  border: 1px solid #d8d2c9;
  border-radius: 6px;
  background: var(--gray-l);
  font-size: 12px;
  color: var(--text);
}
.field-kicker {
  display: block;
  margin-bottom: 6px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--gray-m);
}
.badge-uploading,
.badge-ready,
.badge-error {
  color: white;
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 600;
}
.badge-uploading { background: rgba(0,0,0,.5); }
.badge-ready { background: rgba(0,77,51,.85); }
.badge-error { background: rgba(192,57,43,.85); }

#saveBar {
  display: none;
  position: sticky;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
  border-top: 1px solid #ece7dd;
  padding: 14px 32px;
  z-index: 50;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin: 24px -32px -28px;
}
#saveBar.visible { display: flex; }
#saveBar .sb-info { font-size: 13px; color: var(--gray-m); }
#saveBar .sb-info strong { color: var(--text); }
#saveBar .sb-right { display: flex; gap: 10px; align-items: center; }

.defaults-bar {
  background: white;
  border: 1px solid #ece7dd;
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 20px;
  display: flex;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.defaults-bar label { font-size: 11px; font-weight: 600; color: var(--gray-d); }
.defaults-bar select,
.defaults-bar input { width: 180px; font-size: 12px; padding: 7px 10px; }
.defaults-bar .wide-input { width: 240px; }
.bulk-category-field { width: min(100%, 420px); flex: 1 1 340px; }
.bulk-category-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
.bulk-category-count { font-size: 11px; color: var(--gray-m); font-weight: 500; }
.bulk-category-picker {
  border: 1px solid #d8d2c9;
  border-radius: 8px;
  background: var(--gray-l);
  padding: 8px 10px;
  max-height: 210px;
  overflow-y: auto;
}
.bulk-category-empty { font-size: 12px; color: var(--gray-m); padding: 8px 2px; }
.bulk-category-group { border-bottom: 1px solid #ece7dd; padding: 5px 0; }
.bulk-category-group:last-child { border-bottom: none; }
.bulk-category-row { display: flex; align-items: center; gap: 8px; min-height: 28px; }
.bulk-category-toggle {
  width: 18px;
  height: 18px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--gray-d);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.bulk-category-toggle[aria-expanded="true"] { color: var(--green); }
.bulk-category-toggle[aria-expanded="true"] svg { transform: rotate(90deg); }
.bulk-category-toggle svg { transition: transform .2s ease; }
.bulk-category-spacer { width: 18px; flex-shrink: 0; }
.bulk-category-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 12px;
  color: var(--text);
  flex: 1;
}
.bulk-category-label.is-parent { font-weight: 600; }
.bulk-category-label input { width: auto; margin: 0; accent-color: var(--green); }
.bulk-category-label small { color: var(--gray-m); font-weight: 400; }
.bulk-category-node { padding-left: calc(var(--cat-level) * 14px); }
.bulk-category-children { display: none; padding: 4px 0 4px 20px; }
.bulk-category-children.is-open { display: block; }
.bulk-category-children .bulk-category-label { padding: 4px 0; color: var(--gray-d); }

#resultToast {
  display: none;
  position: fixed;
  bottom: 28px;
  right: 28px;
  z-index: 999;
  background: var(--green);
  color: white;
  padding: 16px 22px;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
  font-size: 14px;
  font-weight: 500;
  max-width: 320px;
}
#resultToast.error { background: var(--red); }
</style>
CSS);

layout_sidebar('carga-masiva.php');
?>
<div class="main">
<?php layout_topbar('Carga masiva de productos', [
    ['href' => ADMIN_URL . '/dashboard.php', 'label' => '<- Dashboard', 'class' => 'btn btn-outline btn-sm'],
]); ?>
<div class="content">

<div class="defaults-bar">
  <div class="bulk-category-field">
    <div class="bulk-category-head">
      <label>Destino de carga</label>
      <span class="bulk-category-count" id="categoryCount">Sin categorias elegidas</span>
    </div>
    <div class="bulk-category-picker" id="bulkCategoryPicker">
      <?php if (empty($catParents)): ?>
        <div class="bulk-category-empty">No hay categorias cargadas.</div>
      <?php else: ?>
        <?php foreach ($catParents as $parent): ?>
          <div class="bulk-category-group">
            <?php render_bulk_category_node($parent, $catChildren); ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <label>Nombre base</label>
    <input type="text" id="bulkBaseName" class="wide-input" placeholder="Ej: APARADOR">
  </div>
  <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer;">
    <input type="checkbox" id="defRentalOnly" style="width:auto;margin:0;">
    Solo alquiler
  </label>
  <button class="btn btn-outline btn-sm" onclick="applyDefaults()">Aplicar a todos</button>
  <span style="font-size:12px;color:var(--gray-m);margin-left:auto;">
    Los productos se guardan siempre como activos
  </span>
</div>

<div class="upload-area" id="dropZone">
  <input type="file" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif">
  <div class="ua-icon">[+]</div>
  <h2>Arrastra las fotos aca</h2>
  <p>O hace clic para seleccionar. JPG, PNG, WEBP. Max <?= MAX_UPLOAD_MB ?>MB por imagen.<br>
     Cada foto crea un producto separado dentro del mismo destino.</p>
  <span class="ua-btn">Seleccionar fotos</span>
</div>

<div id="globalProgress">
  <div class="gp-bar"><div class="gp-fill" id="gpFill"></div></div>
  <div class="gp-text" id="gpText">Subiendo imagenes...</div>
</div>

<div id="cardsGrid"></div>

<div id="saveBar">
  <div class="sb-info">
    <strong id="readyCount">0</strong> productos listos para guardar
    <span id="pendingInfo" style="display:none;color:var(--gold);"> · <strong id="pendingCount">0</strong> subiendo aun</span>
  </div>
  <div class="sb-right">
    <button class="btn btn-outline btn-sm" onclick="clearAll()">Limpiar todo</button>
    <button class="btn btn-primary" id="saveAllBtn" onclick="saveAll()">Guardar todos los productos</button>
  </div>
</div>

</div>
</div>

<div id="resultToast"></div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const UPLOAD_URL = '<?= ADMIN_URL ?>/upload-temp.php';
const SAVE_URL = '<?= ADMIN_URL ?>/bulk-save.php';
const CATEGORIES = <?= json_encode(array_column($categories, 'name', 'id')) ?>;

const cards = new Map();
let uploadQueue = 0;
let gpTotal = 0;
let gpDone = 0;

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const categoryCheckboxes = Array.from(document.querySelectorAll('.bulk-category-checkbox'));

dropZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  dropZone.classList.add('drag');
});

dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag'));

dropZone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropZone.classList.remove('drag');
  handleFiles(Array.from(e.dataTransfer.files));
});

fileInput.addEventListener('change', () => {
  handleFiles(Array.from(fileInput.files));
  fileInput.value = '';
});

categoryCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
  updateCategoryCount();
  applyDefaults();
}));
document.getElementById('bulkBaseName').addEventListener('input', applyDefaults);
document.getElementById('defRentalOnly').addEventListener('change', applyDefaults);
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
updateCategoryCount();

function handleFiles(files) {
  const imgs = files.filter((file) => file.type.startsWith('image/'));
  if (!imgs.length) return;

  if (!getSelectedCategoryIds().length) {
    showToast('Elegi primero las categorias o subcategorias donde queres cargar las fotos', true);
    return;
  }

  showGlobalProgress(imgs.length);
  imgs.forEach(uploadFile);
}

function uploadFile(file) {
  const tmpId = 'tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2);
  uploadQueue++;
  updateSaveBar();

  const card = createCard(tmpId);
  document.getElementById('cardsGrid').appendChild(card.el);
  applyDefaultsToCard(card.el);

  const formData = new FormData();
  formData.append('image', file);

  const xhr = new XMLHttpRequest();
  xhr.open('POST', UPLOAD_URL);

  xhr.upload.onprogress = (e) => {
    if (!e.lengthComputable) return;
    const pct = Math.round((e.loaded / e.total) * 100);
    const bar = card.el.querySelector('.img-progress-fill');
    if (bar) bar.style.width = pct + '%';
  };

  xhr.onload = () => {
    uploadQueue--;
    advanceGlobalProgress();

    try {
      const res = JSON.parse(xhr.responseText);
      if (res.ok) {
        card.data.token = res.token;
        card.data.ready = true;

        const nameInput = card.el.querySelector('.prod-name');
        if (nameInput && !nameInput.dataset.edited && !document.getElementById('bulkBaseName').value.trim()) {
          nameInput.value = res.suggested || '';
        }

        applyDefaultsToCard(card.el);

        const thumbWrap = card.el.querySelector('.card-thumb');
        thumbWrap.innerHTML = '<img src="' + res.thumb_url + '" alt="" loading="lazy">';
        card.el.querySelector('.card-status').innerHTML = '<span class="badge-ready">Lista</span>';
      } else {
        markCardError(card, res.error || 'Error desconocido');
      }
    } catch (err) {
      markCardError(card, 'Respuesta invalida del servidor');
    }

    cards.set(card.data.tmpId, card.data);
    updateSaveBar();
  };

  xhr.onerror = () => {
    uploadQueue--;
    advanceGlobalProgress();
    markCardError(card, 'Error de red');
    updateSaveBar();
  };

  xhr.send(formData);
  card.data.tmpId = tmpId;
  cards.set(tmpId, card.data);
}

function createCard(tmpId) {
  const el = document.createElement('div');
  el.className = 'prod-card';
  el.dataset.tmpId = tmpId;
  el.innerHTML = `
    <div class="card-thumb">
      <div class="loading-overlay">
        <div class="spinner" style="border-color:rgba(0,77,51,.2);border-top-color:var(--green);width:24px;height:24px;border-width:3px;"></div>
        <div class="img-progress"><div class="img-progress-fill"></div></div>
      </div>
    </div>
    <div class="card-status"><span class="badge-uploading">Subiendo</span></div>
    <button class="remove-btn" onclick="removeCard('${tmpId}')" title="Quitar">x</button>
    <div class="card-body">
      <input type="text" class="prod-name" placeholder="Nombre del producto *" required>
      <div>
        <span class="field-kicker">Destino</span>
        <div class="prod-destination">Sin categoria</div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;color:var(--text);">
        <input type="checkbox" class="prod-rental-only" style="width:auto;margin:0;">
        Solo alquiler
      </label>
      <div class="row2">
        <div class="prod-type-label prod-destination" style="display:flex;align-items:center;justify-content:center;">Venta</div>
        <input type="number" class="prod-price" placeholder="Precio (opcional)" min="0" step="1000">
      </div>
    </div>`;

  el.querySelector('.prod-name').addEventListener('input', function () {
    this.dataset.edited = '1';
  });
  el.querySelector('.prod-rental-only').addEventListener('change', function () {
    syncCardMode(el);
  });

  return { el, data: { tmpId, token: null, ready: false, error: false } };
}

function applyDefaults() {
  document.querySelectorAll('.prod-card').forEach((card) => applyDefaultsToCard(card));
}

function applyDefaultsToCard(card) {
  const categoryIds = getSelectedCategoryIds();
  const baseName = document.getElementById('bulkBaseName').value.trim();
  const rentalOnly = document.getElementById('defRentalOnly').checked;

  const destination = card.querySelector('.prod-destination');
  if (destination) {
    destination.textContent = formatCategorySelection(categoryIds);
  }

  const rentalOnlyInput = card.querySelector('.prod-rental-only');
  if (rentalOnlyInput) {
    rentalOnlyInput.checked = rentalOnly;
    syncCardMode(card);
  }

  const nameInput = card.querySelector('.prod-name');
  if (baseName && nameInput && !nameInput.dataset.edited) {
    nameInput.value = baseName;
  }
}

function getSelectedCategoryIds() {
  return categoryCheckboxes
    .filter((checkbox) => checkbox.checked)
    .map((checkbox) => checkbox.value);
}

function formatCategorySelection(categoryIds) {
  if (!categoryIds.length) return 'Sin categoria';

  const names = categoryIds
    .map((id) => CATEGORIES[id])
    .filter(Boolean);

  if (names.length <= 2) return names.join(', ');
  return names.slice(0, 2).join(', ') + ' +' + (names.length - 2);
}

function updateCategoryCount() {
  const count = getSelectedCategoryIds().length;
  const label = document.getElementById('categoryCount');
  if (!label) return;

  label.textContent = count
    ? count + ' categoria' + (count !== 1 ? 's' : '') + ' elegida' + (count !== 1 ? 's' : '')
    : 'Sin categorias elegidas';
}

function syncCardMode(card) {
  const rentalOnlyInput = card.querySelector('.prod-rental-only');
  const priceInput = card.querySelector('.prod-price');
  const typeLabel = card.querySelector('.prod-type-label');
  const rentalOnly = Boolean(rentalOnlyInput?.checked);

  if (typeLabel) {
    typeLabel.textContent = rentalOnly ? 'Alquiler' : 'Venta';
  }

  if (priceInput) {
    priceInput.style.display = rentalOnly ? 'none' : '';
    if (rentalOnly) priceInput.value = '';
  }
}

function markCardError(card, message) {
  card.data.error = true;
  card.data.ready = false;
  card.el.querySelector('.card-thumb').innerHTML = '<div style="padding:20px;text-align:center;color:var(--red);font-size:12px;">' + message + '</div>';
  card.el.querySelector('.card-status').innerHTML = '<span class="badge-error">Error</span>';
}

function removeCard(tmpId) {
  if (!cards.has(tmpId)) return;
  document.querySelector('[data-tmp-id="' + tmpId + '"]')?.remove();
  cards.delete(tmpId);
  updateSaveBar();
}

function showGlobalProgress(amount) {
  gpTotal += amount;
  document.getElementById('globalProgress').style.display = 'block';
  updateGP();
}

function advanceGlobalProgress() {
  gpDone++;
  updateGP();
  if (gpDone < gpTotal) return;

  setTimeout(() => {
    document.getElementById('globalProgress').style.display = 'none';
    gpTotal = 0;
    gpDone = 0;
  }, 1200);
}

function updateGP() {
  const pct = gpTotal ? Math.round((gpDone / gpTotal) * 100) : 0;
  document.getElementById('gpFill').style.width = pct + '%';
  document.getElementById('gpText').textContent = gpDone + ' de ' + gpTotal + ' imagenes procesadas';
}

function updateSaveBar() {
  const ready = [...cards.values()].filter((card) => card.ready && !card.error).length;
  const pending = uploadQueue;
  const saveBar = document.getElementById('saveBar');

  saveBar.classList.toggle('visible', ready > 0 || pending > 0);
  document.getElementById('readyCount').textContent = ready;
  document.getElementById('pendingCount').textContent = pending;
  document.getElementById('pendingInfo').style.display = pending > 0 ? '' : 'none';
  document.getElementById('saveAllBtn').disabled = ready === 0 || pending > 0;
}

function clearAll() {
  if (!confirm('Limpiar todas las tarjetas?')) return;
  document.getElementById('cardsGrid').innerHTML = '';
  cards.clear();
  updateSaveBar();
}

async function saveAll() {
  const categoryIds = getSelectedCategoryIds();
  if (!categoryIds.length) {
    showToast('Elegi al menos una categoria o subcategoria antes de guardar', true);
    return;
  }

  const readyCards = [...document.querySelectorAll('.prod-card')].filter((el) => {
    const card = cards.get(el.dataset.tmpId);
    return card && card.ready && !card.error;
  });

  if (!readyCards.length) {
    showToast('No hay productos listos', true);
    return;
  }

  let invalid = false;
  readyCards.forEach((el) => {
    const name = el.querySelector('.prod-name').value.trim();
    if (name) return;
    el.querySelector('.prod-name').style.borderColor = 'var(--red)';
    invalid = true;
  });

  if (invalid) {
    showToast('Completa el nombre de todos los productos', true);
    return;
  }

  const btn = document.getElementById('saveAllBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Guardando...';

  const products = readyCards.map((el) => {
    const current = cards.get(el.dataset.tmpId);
    const rentalOnly = Boolean(el.querySelector('.prod-rental-only')?.checked);
    return {
      token: current.token,
      title: el.querySelector('.prod-name').value.trim(),
      category_id: categoryIds[0] || null,
      category_ids: categoryIds,
      rental_only: rentalOnly ? 1 : 0,
      type: rentalOnly ? 'alquiler' : 'venta',
      price: rentalOnly ? null : (el.querySelector('.prod-price').value || null),
      status: 'activo',
    };
  });

  try {
    const res = await fetch(SAVE_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
      },
      body: JSON.stringify({ products }),
    });
    const data = await res.json();

    if (!data.ok) {
      showToast('Error: ' + (data.error || 'desconocido'), true);
      return;
    }

    const failedTokens = new Set((data.errors || []).map((item) => item.token));
    readyCards.forEach((el) => {
      const current = cards.get(el.dataset.tmpId);
      if (failedTokens.has(current?.token)) return;
      el.remove();
      cards.delete(el.dataset.tmpId);
    });

    let message = data.saved + ' producto' + (data.saved !== 1 ? 's' : '') + ' guardado' + (data.saved !== 1 ? 's' : '');
    if (data.errors?.length) {
      message += ' · ' + data.errors.length + ' con error';
    }
    showToast(message, Boolean(data.errors?.length));
    updateSaveBar();
  } catch (err) {
    showToast('Error de red al guardar', true);
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Guardar todos los productos';
  }
}

function showToast(message, isError = false) {
  const toast = document.getElementById('resultToast');
  toast.textContent = message;
  toast.className = isError ? 'error' : '';
  toast.style.display = 'block';
  setTimeout(() => {
    toast.style.display = 'none';
  }, 4000);
}
</script>
<?php layout_foot(); ?>
