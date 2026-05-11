<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';
auth_require();

$db = db();

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
$search     = trim($_GET['q'] ?? '');
$catId      = (int)($_GET['cat'] ?? 0);
$status     = $_GET['status'] ?? '';
$rentalOnly = isset($_GET['rental_only']) ? 1 : 0;
$page       = max(1, (int)($_GET['page'] ?? 1));
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
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT p.*, c.name as cat_name,
           (SELECT path_thumb FROM product_images WHERE product_id = p.id AND is_cover = 1 LIMIT 1) as cover_thumb,
           (SELECT path_thumb FROM product_images WHERE product_id = p.id ORDER BY position LIMIT 1) as first_thumb
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereStr
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $perPage, $offset]);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY position, name")->fetchAll();

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

<div class="card" style="margin-bottom:20px;">
  <form method="GET" style="padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:2;min-width:180px;">
      <label style="margin-bottom:4px;display:block;">Buscar</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Nombre del producto...">
    </div>
    <div style="flex:1;min-width:140px;">
      <label style="margin-bottom:4px;display:block;">Categoria</label>
      <select name="cat">
        <option value="">Todas</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
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
    <?php if ($search || $catId || $status || $rentalOnly): ?>
      <a href="dashboard.php" class="btn btn-outline btn-sm">Limpiar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <h3><?= $total ?> producto<?= $total !== 1 ? 's' : '' ?></h3>
    <span class="text-sm text-m">Pagina <?= $page ?> de <?= $pages ?></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:64px;">Foto</th>
          <th>Nombre</th>
          <th>Categoria</th>
          <th style="width:120px;">Modalidad</th>
          <th style="width:120px;">Precio</th>
          <th style="width:140px;">Estado</th>
          <th style="width:60px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--gray-m);">No se encontraron productos.</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p):
            $thumb = $p['cover_thumb'] ?? $p['first_thumb'] ?? null;
        ?>
        <tr>
          <td>
            <?php if ($thumb): ?>
              <img src="/Montepio/<?= h($thumb) ?>" class="td-thumb" alt="">
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
  <div style="padding:16px 20px;display:flex;gap:6px;align-items:center;border-top:1px solid #ece7dd;">
    <?php
    $query = ['q' => $search, 'cat' => $catId ?: null, 'status' => $status ?: null, 'rental_only' => $rentalOnly ? 1 : null];
    $qs = http_build_query(array_filter($query, static fn($value) => $value !== null && $value !== ''));
    for ($i = 1; $i <= $pages; $i++):
    ?>
      <a href="?<?= $qs ?>&page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
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
</style>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;

function saveInline(field, id, value) {
  field.classList.add('saving');
  fetch('inline-save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ id, field: field.dataset.field, value })
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
</script>
<?php layout_foot(); ?>
