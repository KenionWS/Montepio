<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';
auth_require();

$db = db();

const PRODUCT_FILTERS_SESSION_KEY = 'admin_product_filters';

function dashboard_category_has_selected_descendant(array $category, array $childrenByParent, int $selectedId): bool
{
    foreach ($childrenByParent[$category['id']] ?? [] as $child) {
        if ((int)$child['id'] === $selectedId || dashboard_category_has_selected_descendant($child, $childrenByParent, $selectedId)) {
            return true;
        }
    }

    return false;
}

function dashboard_category_search_text(array $category, array $childrenByParent): string
{
    $text = (string)$category['name'] . ' ' . (string)($category['slug'] ?? '');
    foreach ($childrenByParent[$category['id']] ?? [] as $child) {
        $text .= ' ' . dashboard_category_search_text($child, $childrenByParent);
    }

    return strtolower(trim($text));
}

function render_dashboard_category_filter_node(array $category, array $childrenByParent, int $selectedId, int $level = 0): void
{
    $children = $childrenByParent[$category['id']] ?? [];
    $hasChildren = !empty($children);
    $isExpanded = $selectedId > 0 && dashboard_category_has_selected_descendant($category, $childrenByParent, $selectedId);
    $isSelected = $selectedId === (int)$category['id'];
    $label = $level === 0 ? 'Principal' : 'Nivel ' . ($level + 1);
    $searchText = dashboard_category_search_text($category, $childrenByParent);
    ?>
    <div class="filter-tree-node" data-filter-node data-search="<?= h($searchText) ?>" style="--filter-level: <?= $level ?>">
      <div class="filter-tree-row">
        <?php if ($hasChildren): ?>
          <button type="button"
                  class="filter-tree-toggle"
                  data-filter-toggle
                  aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                  aria-controls="filter-tree-children-<?= (int)$category['id'] ?>">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></svg>
          </button>
        <?php else: ?>
          <span class="filter-tree-toggle-spacer"></span>
        <?php endif; ?>

        <button type="button"
                class="filter-tree-option <?= $isSelected ? 'is-selected' : '' ?>"
                data-category-option
                data-category-id="<?= (int)$category['id'] ?>"
                data-category-name="<?= h($category['name']) ?>">
          <span class="filter-tree-option-name"><?= h($category['name']) ?></span>
          <span class="filter-tree-option-meta"><?= h($label) ?></span>
        </button>
      </div>

      <?php if ($hasChildren): ?>
        <div id="filter-tree-children-<?= (int)$category['id'] ?>" class="filter-tree-children <?= $isExpanded ? 'is-open' : '' ?>">
          <?php foreach ($children as $child): ?>
            <?php render_dashboard_category_filter_node($child, $childrenByParent, $selectedId, $level + 1); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

// Stats
$stats = $db->query("
    SELECT
      COUNT(*) as total,
      SUM(status = 'activo')    as activos,
      SUM(status = 'reservado') as reservados,
      SUM(status = 'vendido')   as vendidos,
      SUM(rental_only = 1)      as solo_alquiler
    FROM products
")->fetch();

// Filtros
$allowedStatuses = ['activo', 'reservado', 'vendido'];
$hasSubmittedFilters = isset($_GET['apply_filters']);
$resetFilters = isset($_GET['reset_filters']);
$savedFilters = is_array($_SESSION[PRODUCT_FILTERS_SESSION_KEY] ?? null) ? $_SESSION[PRODUCT_FILTERS_SESSION_KEY] : [];

if ($resetFilters) {
    unset($_SESSION[PRODUCT_FILTERS_SESSION_KEY]);
    $savedFilters = [];
}

$filterSource = $hasSubmittedFilters
    ? $_GET
    : $savedFilters;

$search = trim((string)($filterSource['q'] ?? ''));
$catId = max(0, (int)($filterSource['cat'] ?? 0));
$status = (string)($filterSource['status'] ?? '');
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
$rentalOnly = !empty($filterSource['rental_only']) ? 1 : 0;
$page = max(1, (int)($_GET['page'] ?? 1));

if ($hasSubmittedFilters) {
    $filtersToPersist = [
        'q' => $search,
        'cat' => $catId,
        'status' => $status,
        'rental_only' => $rentalOnly,
    ];
    $hasActiveFilters = $search !== '' || $catId > 0 || $status !== '' || $rentalOnly === 1;
    if ($hasActiveFilters) {
        $_SESSION[PRODUCT_FILTERS_SESSION_KEY] = $filtersToPersist;
    } else {
        unset($_SESSION[PRODUCT_FILTERS_SESSION_KEY]);
    }
}

$perPage    = 20;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.title LIKE ?)'; $params[] = "%$search%"; }
if ($catId)  { $where[] = 'EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?)'; $params[] = $catId; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($rentalOnly) { $where[] = 'p.rental_only = 1'; }

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$perPage = $catId ? max($total, 1) : $perPage;
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$categoryPositionSelect = $catId ? ', COALESCE(filter_pc.position, 0) AS category_position' : ', NULL AS category_position';
$categoryPositionJoin = $catId
    ? 'LEFT JOIN product_categories filter_pc ON filter_pc.product_id = p.id AND filter_pc.category_id = ?'
    : '';
$orderBy = $catId
    ? 'COALESCE(filter_pc.position, 0) ASC, p.is_featured DESC, p.created_at DESC, p.id DESC'
    : 'p.created_at DESC, p.id DESC';
$listParams = $catId ? array_merge([$catId], $params, [$perPage, $offset]) : array_merge($params, [$perPage, $offset]);

$stmt = $db->prepare("
    SELECT p.*, c.name as cat_name{$categoryPositionSelect},
           (SELECT path_thumb FROM product_images WHERE product_id = p.id AND is_cover = 1 LIMIT 1) as cover_thumb,
           (SELECT path_thumb FROM product_images WHERE product_id = p.id ORDER BY position LIMIT 1) as first_thumb
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    {$categoryPositionJoin}
    WHERE $whereStr
    ORDER BY {$orderBy}
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY LOWER(name), name")->fetchAll();
$categoryParents = array_values(array_filter($categories, static function (array $category): bool {
    return $category['parent_id'] === null;
}));
$categoryChildren = [];
foreach ($categories as $category) {
    if ($category['parent_id'] !== null) {
        $categoryChildren[$category['parent_id']][] = $category;
    }
}
$selectedCategory = null;
foreach ($categories as $category) {
    if ((int)$category['id'] === $catId) {
        $selectedCategory = $category;
        break;
    }
}
$currentUrl = ADMIN_URL . '/dashboard.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');

layout_head('Dashboard');
layout_sidebar('dashboard.php');
?>
<div class="main">
<?php layout_topbar('Productos', [
    ['href' => ADMIN_URL . '/producto.php',     'label' => 'Nuevo producto', 'class' => 'btn btn-outline btn-sm'],
    ['href' => ADMIN_URL . '/carga-masiva.php', 'label' => '<- Carga masiva', 'class' => 'btn btn-primary btn-sm'],
]); ?>
<div class="content">
<?php layout_flash(); ?>

<div class="kpi-grid">
  <div class="kpi kpi-green"><div class="kpi-val"><?= $stats['total'] ?></div><div class="kpi-label">Total productos</div></div>
  <div class="kpi"><div class="kpi-val"><?= $stats['activos'] ?></div><div class="kpi-label">Activos</div></div>
  <div class="kpi kpi-gold"><div class="kpi-val"><?= $stats['reservados'] ?></div><div class="kpi-label">Reservados</div></div>
  <div class="kpi kpi-red"><div class="kpi-val"><?= $stats['vendidos'] ?></div><div class="kpi-label">Vendidos</div></div>
</div>

<div class="card" style="margin-bottom:20px;overflow: visible;">
  <form method="GET" style="padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="apply_filters" value="1">
    <div style="flex:2;min-width:180px;">
      <label style="margin-bottom:4px;display:block;">Buscar</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Nombre del producto...">
    </div>
    <div style="flex:1.4;min-width:240px;">
      <label style="margin-bottom:4px;display:block;">Categoria</label>
      <div class="category-filter-picker" data-category-filter-picker>
        <input type="hidden" name="cat" value="<?= $catId ?: '' ?>" id="categoryFilterInput">
        <button type="button" class="category-filter-trigger" data-category-filter-trigger aria-expanded="false">
          <span data-category-filter-label><?= h($selectedCategory['name'] ?? 'Todas las categorias') ?></span>
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
        </button>
        <div class="category-filter-panel" data-category-filter-panel hidden>
          <div class="category-filter-search">
            <input type="search" placeholder="Buscar categoria o subcategoria..." data-category-filter-search autocomplete="off">
          </div>
          <div class="category-filter-actions">
            <button type="button" class="category-filter-clear" data-category-clear>Ver todas</button>
          </div>
          <div class="category-filter-tree" data-category-filter-tree>
            <?php foreach ($categoryParents as $parent): ?>
              <?php render_dashboard_category_filter_node($parent, $categoryChildren, $catId); ?>
            <?php endforeach; ?>
          </div>
          <div class="category-filter-empty" data-category-empty hidden>No encontramos categorias con esa busqueda.</div>
        </div>
      </div>
    </div>
    <div style="flex:1;min-width:120px;">
      <label style="margin-bottom:4px;display:block;">Estado</label>
      <select name="status">
        <option value="">Todos</option>
        <option value="activo" <?= $status === 'activo' ? 'selected' : '' ?>>Activo</option>
        <option value="reservado" <?= $status === 'reservado' ? 'selected' : '' ?>>Reservado</option>
        <option value="vendido" <?= $status === 'vendido' ? 'selected' : '' ?>>Vendido</option>
      </select>
    </div>
    <label style="display:flex;align-items:center;gap:8px;padding:10px 0;cursor:pointer;">
      <input type="checkbox" name="rental_only" value="1" <?= $rentalOnly ? 'checked' : '' ?> style="width:auto;margin:0;">
      Solo alquiler
    </label>
    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    <?php if ($search || $catId || $status || $rentalOnly || !empty($savedFilters)): ?>
      <a href="dashboard.php?reset_filters=1" class="btn btn-outline btn-sm">Limpiar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <div>
      <h3><?= $total ?> producto<?= $total !== 1 ? 's' : '' ?></h3>
      <span class="text-sm text-m"><?= $catId ? 'Lista completa lista para reordenar por arrastre.' : 'Pagina ' . $page . ' de ' . $pages ?></span>
      <?php if ($catId): ?>
        <div class="text-sm text-m" style="margin-top:4px;">Arrastra las filas desde el icono para definir el orden del front en esta categoria.</div>
      <?php endif; ?>
    </div>
    <form method="POST" action="eliminar.php" id="bulk-delete-form" onsubmit="return confirmBulkDelete()">
      <?= csrf_input() ?>
      <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
      <button type="submit" class="btn btn-danger btn-sm" id="bulk-delete-btn" disabled>
        Eliminar seleccionados
      </button>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:44px;"><input type="checkbox" id="select-all-products" aria-label="Seleccionar todos"></th>
          <?php if ($catId): ?><th style="width:44px;"></th><?php endif; ?>
          <th style="width:64px;">Foto</th>
          <th>Nombre</th>
          <th>Categoria</th>
          <th style="width:120px;">Modalidad</th>
          <th style="width:120px;">Precio</th>
          <th style="width:140px;">Estado</th>
          <th style="width:60px;">Acciones</th>
        </tr>
      </thead>
      <tbody <?= $catId ? 'id="sortable-products-body"' : '' ?>>
        <?php if (empty($products)): ?>
        <tr><td colspan="<?= $catId ? 9 : 8 ?>" style="text-align:center;padding:32px;color:var(--gray-m);">No se encontraron productos.</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p):
            $thumb = $p['cover_thumb'] ?? $p['first_thumb'] ?? null;
        ?>
        <tr <?= $catId ? 'class="sortable-product-row" draggable="true" data-product-id="' . (int)$p['id'] . '"' : '' ?>>
          <td>
            <input type="checkbox" name="ids[]" value="<?= $p['id'] ?>" class="product-check" form="bulk-delete-form" aria-label="Seleccionar <?= h($p['title']) ?>">
          </td>
          <?php if ($catId): ?>
          <td>
            <button type="button" class="drag-handle" aria-label="Reordenar <?= h($p['title']) ?>" title="Arrastrar para reordenar">
              <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1"></circle><circle cx="15" cy="6" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="9" cy="18" r="1"></circle><circle cx="15" cy="18" r="1"></circle></svg>
            </button>
          </td>
          <?php endif; ?>
          <td>
            <?php if ($thumb): ?>
              <img src="<?= BASE_URL ?>/<?= h($thumb) ?>" class="td-thumb" alt="">
            <?php else: ?>
              <div class="td-thumb-placeholder">[]</div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <input type="text" class="inline-field" data-id="<?= $p['id'] ?>" data-field="title"
                     value="<?= h($p['title']) ?>" style="font-weight:600;min-width:160px;">
              <?php if ($p['is_featured']): ?><span class="badge badge-gold" style="font-size:10px;">*</span><?php endif; ?>
            </div>
          </td>
          <td><?= h($p['cat_name'] ?? '-') ?></td>
          <td>
            <?php if (!empty($p['rental_only'])): ?>
              <span class="badge badge-gold">Solo alquiler</span>
            <?php else: ?>
              <span class="badge badge-green">Venta</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($p['rental_only'])): ?>
              <span class="text-sm text-m">-</span>
            <?php else: ?>
              <input type="number" class="inline-field" data-id="<?= $p['id'] ?>" data-field="price"
                     value="<?= h((string)($p['price'] ?? '')) ?>" placeholder="Consultar" min="0" step="1000"
                     style="width:110px;">
            <?php endif; ?>
          </td>
          <td>
            <select class="inline-field inline-select" data-id="<?= $p['id'] ?>" data-field="status">
              <option value="activo" <?= $p['status'] === 'activo' ? 'selected' : '' ?>>Activo</option>
              <option value="reservado" <?= $p['status'] === 'reservado' ? 'selected' : '' ?>>Reservado</option>
              <option value="vendido" <?= $p['status'] === 'vendido' ? 'selected' : '' ?>>Vendido</option>
            </select>
          </td>
          <td>
            <div style="display:flex;gap:6px;">
              <a href="producto.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-icon btn-sm" title="Editar">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form method="POST" action="eliminar.php" onsubmit="return confirm('Eliminar <?= h(addslashes($p['title'])) ?>? Esta accion no se puede deshacer.')">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="redirect" value="<?= h($currentUrl) ?>">
                <button type="submit" class="btn btn-danger btn-icon btn-sm" title="Eliminar">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php
    $query = ['apply_filters' => 1, 'q' => $search, 'cat' => $catId ?: null, 'status' => $status ?: null, 'rental_only' => $rentalOnly ? 1 : null];
    $baseQuery = array_filter($query, static function ($value): bool {
        return $value !== null && $value !== '';
    });
    $pageUrl = static function (int $targetPage) use ($baseQuery): string {
        return '?' . http_build_query($baseQuery + ['page' => $targetPage]);
    };
    $pageItems = [1, $pages];
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $pageItems[] = $i;
    }
    $pageItems = array_values(array_unique($pageItems));
    sort($pageItems);
    ?>
    <a href="<?= $pageUrl(max(1, $page - 1)) ?>" class="btn btn-sm btn-outline <?= $page === 1 ? 'pager-disabled' : '' ?>">Anterior</a>
    <?php $previousItem = 0; ?>
    <?php foreach ($pageItems as $i): ?>
      <?php if ($previousItem && $i > $previousItem + 1): ?>
        <span class="pager-ellipsis">...</span>
      <?php endif; ?>
      <a href="<?= $pageUrl($i) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>">
        <?= $i ?>
      </a>
      <?php $previousItem = $i; ?>
    <?php endforeach; ?>
    <a href="<?= $pageUrl(min($pages, $page + 1)) ?>" class="btn btn-sm btn-outline <?= $page === $pages ? 'pager-disabled' : '' ?>">Siguiente</a>
  </div>
  <?php endif; ?>
</div>

</div>
</div>

<style>
.inline-field {
  border: 1px solid transparent; background: transparent; border-radius: 6px;
  padding: 4px 7px; font-size: 13px; color: var(--text); font-family: inherit;
  transition: border-color .15s, background .15s;
}
.inline-field:hover { border-color: #d8d2c9; background: var(--gray-l); }
.inline-field:focus { border-color: var(--green); background: white; outline: none; box-shadow: 0 0 0 3px rgba(0,77,51,.1); }
.inline-field.saving { opacity: .5; pointer-events: none; }
.inline-field.saved  { border-color: #27ae60; background: #f0faf4; }
.inline-field.error  { border-color: var(--red); background: var(--red-l); }
.inline-select { cursor: pointer; }
.product-check,
#select-all-products { width:16px; height:16px; cursor:pointer; }
#bulk-delete-btn:disabled { opacity:.45; cursor:not-allowed; transform:none; }
.pager {
  padding:16px 20px;
  display:flex;
  gap:6px;
  align-items:center;
  flex-wrap:wrap;
  border-top:1px solid #ece7dd;
}
.pager .btn { min-width:32px; justify-content:center; }
.pager-disabled {
  opacity:.45;
  pointer-events:none;
}
.pager-ellipsis {
  color:var(--gray-m);
  padding:0 4px;
}
.category-filter-picker { position:relative; }
.category-filter-trigger {
  width:100%; min-height:42px; border:1px solid #d8d2c9; border-radius:10px; background:#fff; color:var(--text);
  padding:10px 14px; display:flex; align-items:center; justify-content:space-between; gap:12px; cursor:pointer; font:inherit;
}
.category-filter-trigger:hover,
.category-filter-trigger[aria-expanded="true"] { border-color:var(--green); }
.category-filter-panel {
  position:absolute; top:calc(100% + 6px); left:0; right:0; z-index:30; background:#fff; border:1px solid #d8d2c9;
  border-radius:14px; box-shadow:0 18px 40px rgba(0,0,0,.12); padding:10px; max-height:360px; overflow:hidden;
}
.category-filter-search input {
  width:100%; border:1px solid #d8d2c9; border-radius:10px; padding:10px 12px; font:inherit;
}
.category-filter-actions { display:flex; justify-content:flex-end; padding:8px 2px 6px; }
.category-filter-clear {
  border:none; background:transparent; color:var(--green); font-weight:600; cursor:pointer; padding:4px 6px;
}
.category-filter-tree { max-height:270px; overflow:auto; padding-right:4px; }
.category-filter-node { margin-bottom:4px; }
.filter-tree-row { display:flex; align-items:center; gap:8px; padding-left:calc(var(--filter-level) * 14px); }
.filter-tree-toggle, .filter-tree-toggle-spacer {
  width:18px; height:18px; flex:0 0 18px; display:inline-flex; align-items:center; justify-content:center;
}
.filter-tree-toggle {
  border:none; background:transparent; color:var(--gray-d); cursor:pointer; padding:0;
}
.filter-tree-toggle svg { transition:transform .2s ease; }
.filter-tree-toggle[aria-expanded="true"] svg { transform:rotate(90deg); }
.filter-tree-option {
  flex:1; border:none; background:#f8f5ef; border-radius:10px; padding:9px 10px; text-align:left; cursor:pointer;
  display:flex; align-items:center; justify-content:space-between; gap:10px; color:var(--text);
}
.filter-tree-option:hover,
.filter-tree-option.is-selected { background:var(--green-light); color:var(--green); }
.filter-tree-option-name { font-weight:600; }
.filter-tree-option-meta { font-size:11px; text-transform:uppercase; letter-spacing:.08em; opacity:.72; }
.filter-tree-children { display:none; padding-top:4px; }
.filter-tree-children.is-open { display:block; }
.category-filter-empty { padding:12px 8px 4px; color:var(--gray-m); font-size:13px; }
.sortable-product-row.dragging { opacity:.45; }
.sortable-product-row.drag-over { box-shadow:inset 0 3px 0 var(--green); }
.drag-handle {
  width:30px; height:30px; border:none; border-radius:8px; background:#f3eee5; color:var(--gray-d); cursor:grab;
  display:inline-flex; align-items:center; justify-content:center;
}
.drag-handle:hover { color:var(--green); background:var(--green-light); }
.drag-handle:active { cursor:grabbing; }
.is-reordering .drag-handle,
.is-reordering .inline-field,
.is-reordering .inline-select { pointer-events:none; }
</style>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const ACTIVE_CATEGORY_ID = <?= $catId ?>;

function saveInline(field, id, value) {
  const payload = { id, field: field.dataset.field, value };
  if (field.dataset.categoryId) {
    payload.category_id = parseInt(field.dataset.categoryId, 10);
  }
  field.classList.add('saving');
  fetch('inline-save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    field.classList.remove('saving');
    if (data.ok) {
      field.classList.add('saved');
      setTimeout(() => field.classList.remove('saved'), 1500);
    } else {
      field.classList.add('error');
      setTimeout(() => field.classList.remove('error'), 2000);
      field.title = data.error ?? 'Error';
    }
  })
  .catch(() => {
    field.classList.remove('saving');
    field.classList.add('error');
    setTimeout(() => field.classList.remove('error'), 2000);
  });
}

document.querySelectorAll('.inline-field').forEach(el => {
  const id = parseInt(el.dataset.id, 10);

  if (el.tagName === 'SELECT') {
    el.addEventListener('change', () => saveInline(el, id, el.value));
  } else {
    let original = el.value;
    el.addEventListener('focus', () => { original = el.value; });
    el.addEventListener('blur', () => {
      if (el.value !== original) saveInline(el, id, el.value);
    });
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
      if (e.key === 'Escape') { el.value = original; el.blur(); }
    });
  }
});

const selectAllProducts = document.getElementById('select-all-products');
const productChecks = Array.from(document.querySelectorAll('.product-check'));
const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

function updateBulkDeleteState() {
  const selected = productChecks.filter(check => check.checked).length;
  if (bulkDeleteBtn) {
    bulkDeleteBtn.disabled = selected === 0;
    bulkDeleteBtn.textContent = selected > 0 ? `Eliminar seleccionados (${selected})` : 'Eliminar seleccionados';
  }
  if (selectAllProducts) {
    selectAllProducts.checked = selected > 0 && selected === productChecks.length;
    selectAllProducts.indeterminate = selected > 0 && selected < productChecks.length;
  }
}

function confirmBulkDelete() {
  const selected = productChecks.filter(check => check.checked).length;
  if (selected === 0) return false;
  return confirm(`Eliminar ${selected} producto${selected !== 1 ? 's' : ''} seleccionado${selected !== 1 ? 's' : ''}? Esta accion no se puede deshacer.`);
}

if (selectAllProducts) {
  selectAllProducts.addEventListener('change', () => {
    productChecks.forEach(check => { check.checked = selectAllProducts.checked; });
    updateBulkDeleteState();
  });
}
productChecks.forEach(check => check.addEventListener('change', updateBulkDeleteState));
updateBulkDeleteState();

const categoryPicker = document.querySelector('[data-category-filter-picker]');
if (categoryPicker) {
  const trigger = categoryPicker.querySelector('[data-category-filter-trigger]');
  const panel = categoryPicker.querySelector('[data-category-filter-panel]');
  const searchInput = categoryPicker.querySelector('[data-category-filter-search]');
  const hiddenInput = document.getElementById('categoryFilterInput');
  const label = categoryPicker.querySelector('[data-category-filter-label]');
  const clearButton = categoryPicker.querySelector('[data-category-clear]');
  const emptyState = categoryPicker.querySelector('[data-category-empty]');
  const nodes = Array.from(categoryPicker.querySelectorAll('[data-filter-node]'));

  const applyCategoryFilterSearch = () => {
    const query = (searchInput.value || '').trim().toLowerCase();
    let visible = 0;
    nodes.forEach(node => {
      const match = query === '' || (node.dataset.search || '').includes(query);
      node.hidden = !match;
      if (match) visible++;
    });
    emptyState.hidden = visible !== 0;
  };

  const openPanel = () => {
    panel.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    searchInput.focus();
    applyCategoryFilterSearch();
  };

  const closePanel = () => {
    panel.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    searchInput.value = '';
    applyCategoryFilterSearch();
  };

  trigger.addEventListener('click', () => {
    if (panel.hidden) openPanel(); else closePanel();
  });

  document.addEventListener('click', event => {
    if (!categoryPicker.contains(event.target)) closePanel();
  });

  searchInput.addEventListener('input', applyCategoryFilterSearch);

  clearButton.addEventListener('click', () => {
    hiddenInput.value = '';
    label.textContent = 'Todas las categorias';
    categoryPicker.querySelectorAll('[data-category-option]').forEach(option => option.classList.remove('is-selected'));
    closePanel();
  });

  categoryPicker.querySelectorAll('[data-category-option]').forEach(option => {
    option.addEventListener('click', () => {
      hiddenInput.value = option.dataset.categoryId;
      label.textContent = option.dataset.categoryName;
      categoryPicker.querySelectorAll('[data-category-option]').forEach(item => item.classList.remove('is-selected'));
      option.classList.add('is-selected');
      closePanel();
    });
  });

  categoryPicker.querySelectorAll('[data-filter-toggle]').forEach(toggle => {
    toggle.addEventListener('click', () => {
      const container = document.getElementById(toggle.getAttribute('aria-controls'));
      const isOpen = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      container.classList.toggle('is-open', !isOpen);
    });
  });
}

async function saveCategoryOrder(productIds) {
  const response = await fetch('inline-save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({
      field: 'category_reorder',
      category_id: ACTIVE_CATEGORY_ID,
      product_ids: productIds
    })
  });
  return response.json();
}

if (ACTIVE_CATEGORY_ID > 0) {
  const tbody = document.getElementById('sortable-products-body');
  const rows = tbody ? Array.from(tbody.querySelectorAll('.sortable-product-row')) : [];
  let draggedRow = null;

  const persistOrder = async () => {
    if (!tbody) return;
    const orderedIds = Array.from(tbody.querySelectorAll('.sortable-product-row')).map(row => parseInt(row.dataset.productId, 10));
    tbody.classList.add('is-reordering');
    try {
      const data = await saveCategoryOrder(orderedIds);
      if (!data.ok) throw new Error(data.error || 'Error');
      tbody.querySelectorAll('.sortable-product-row').forEach(row => row.classList.remove('drag-over'));
    } catch (error) {
      window.location.reload();
    } finally {
      tbody.classList.remove('is-reordering');
    }
  };

  rows.forEach(row => {
    row.addEventListener('dragstart', event => {
      draggedRow = row;
      row.classList.add('dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', row.dataset.productId);
    });

    row.addEventListener('dragend', () => {
      row.classList.remove('dragging');
      rows.forEach(item => item.classList.remove('drag-over'));
    });

    row.addEventListener('dragover', event => {
      if (!draggedRow || draggedRow === row) return;
      event.preventDefault();
      row.classList.add('drag-over');
    });

    row.addEventListener('dragleave', () => {
      row.classList.remove('drag-over');
    });

    row.addEventListener('drop', event => {
      if (!draggedRow || draggedRow === row) return;
      event.preventDefault();
      row.classList.remove('drag-over');
      const rect = row.getBoundingClientRect();
      const shouldInsertAfter = event.clientY > rect.top + rect.height / 2;
      if (shouldInsertAfter) {
        row.parentNode.insertBefore(draggedRow, row.nextSibling);
      } else {
        row.parentNode.insertBefore(draggedRow, row);
      }
      persistOrder();
    });
  });
}
</script>
<?php layout_foot(); ?>
