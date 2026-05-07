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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $editId = (int)($_POST['edit_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;

        if ($name === '') {
            flash_set('err', 'El nombre es obligatorio.');
        } else {
            $slug = unique_slug($name, $action === 'edit' ? $editId : 0, 'categories');
            $categoryId = $editId;

            if ($action === 'create') {
                $pos = (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
                $stmt = $db->prepare('INSERT INTO categories (name, slug, description, show_in_menu, parent_id, position) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $slug, $description !== '' ? $description : null, 0, $parentId, $pos]);
                $categoryId = (int)$db->lastInsertId();
                flash_set('ok', 'Categoria creada.');
            } else {
                if ($parentId === $editId) {
                    $parentId = null;
                }

                $stmt = $db->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?');
                $stmt->execute([$name, $slug, $description !== '' ? $description : null, $parentId, $editId]);
                flash_set('ok', 'Categoria actualizada.');
            }

            if ($categoryId > 0 && isset($_FILES['cover_image']) && (int)($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int)$_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $tmp = (string)$_FILES['cover_image']['tmp_name'];
                    $size = (int)$_FILES['cover_image']['size'];
                    $validationError = null;

                    if (image_validate_upload($tmp, $size, $validationError)) {
                        $currentStmt = $db->prepare('SELECT cover_path FROM categories WHERE id = ?');
                        $currentStmt->execute([$categoryId]);
                        $currentPath = (string)($currentStmt->fetchColumn() ?: '');

                        $coverPath = image_process_category_cover($tmp, $categoryId);
                        if ($coverPath !== false) {
                            $db->prepare('UPDATE categories SET cover_path = ? WHERE id = ?')->execute([$coverPath, $categoryId]);
                            if ($currentPath !== '' && $currentPath !== $coverPath && file_exists(ROOT_PATH . '/' . $currentPath)) {
                                @unlink(ROOT_PATH . '/' . $currentPath);
                            }
                        } else {
                            flash_set('err', 'La categoria se guardo, pero no se pudo procesar la portada.');
                        }
                    } else {
                        flash_set('err', 'La categoria se guardo, pero la portada no es valida: ' . $validationError . '.');
                    }
                } else {
                    flash_set('err', 'La categoria se guardo, pero la portada no se pudo subir.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $delId = (int)($_POST['del_id'] ?? 0);

        $prodStmt = $db->prepare('SELECT COUNT(*) FROM product_categories WHERE category_id = ?');
        $prodStmt->execute([$delId]);
        $prodCount = (int)$prodStmt->fetchColumn();

        $subStmt = $db->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
        $subStmt->execute([$delId]);
        $subCount = (int)$subStmt->fetchColumn();

        if ($subCount > 0) {
            flash_set('err', 'No se puede eliminar: tiene subcategorias. Elimina primero las subcategorias.');
        } elseif ($prodCount > 0) {
            flash_set('err', "No se puede eliminar: hay $prodCount producto(s) asociado(s).");
        } else {
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$delId]);
            flash_set('ok', 'Categoria eliminada.');
        }
    }

    header('Location: ' . ADMIN_URL . '/categorias.php');
    exit;
}

$allCats = $db->query('
    SELECT c.*, COUNT(pc.product_id) AS prod_count
    FROM categories c
    LEFT JOIN product_categories pc ON pc.category_id = c.id
    GROUP BY c.id
    ORDER BY c.position, c.name
')->fetchAll();

$parents = array_values(array_filter($allCats, static fn(array $c): bool => $c['parent_id'] === null));
$children = [];
foreach ($allCats as $cat) {
    if ($cat['parent_id'] !== null) {
        $children[$cat['parent_id']][] = $cat;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editCat = null;
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
}

layout_head('Categorias', '<style>
.cat-tree { list-style: none; padding: 0; margin: 0; }
.cat-tree li { border-bottom: 1px solid #ece7dd; }
.cat-tree li:last-child { border-bottom: none; }
.cat-toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:18px 20px 14px; border-bottom:1px solid #ece7dd; }
.cat-search { position:relative; flex:1; max-width:420px; }
.cat-search input { width:100%; padding:11px 14px 11px 38px; border:1px solid #d8d2c9; border-radius:12px; background:#fff; }
.cat-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray-m); }
.cat-toolbar-note { font-size:12px; color:var(--gray-m); white-space:nowrap; }
.cat-empty-filter { display:none; padding:28px 20px; color:var(--gray-m); text-align:center; }
.cat-row { display: flex; align-items: center; gap: 12px; padding: 12px 20px; }
.cat-row.is-parent { cursor:pointer; }
.cat-row.is-child { padding-left: 60px; background: #faf8f4; }
.cat-group-children { display:none; }
.cat-group.is-open .cat-group-children { display:block; }
.cat-toggle { width:30px; height:30px; border:none; border-radius:10px; background:#f3eee5; color:var(--gray-d); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; flex:0 0 auto; transition:background .2s ease,color .2s ease,transform .2s ease; }
.cat-toggle:hover { background:#e9e1d5; color:var(--green); }
.cat-group.is-open .cat-toggle { color:var(--green); }
.cat-group.is-open .cat-toggle svg { transform:rotate(90deg); }
.cat-toggle svg { transition:transform .2s ease; }
.cat-visual { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.cat-icon {
    width: 34px;
    height: 34px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f3eee5;
    border: 1px solid #e3dbcf;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    color: var(--green);
    flex: 0 0 auto;
}
.cat-name { font-weight: 500; display:block; }
.cat-desc { display:block; font-size:12px; color:var(--gray-m); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px; }
.cat-slug { font-size: 11px; color: var(--gray-m); font-family: monospace; }
.cat-badge { font-size: 11px; padding: 2px 8px; background: var(--green-light); color: var(--green); border-radius: 20px; }
.cat-meta { display:flex; gap:6px; align-items:center; }
.cat-actions { display: flex; gap: 6px; }
.cat-group.is-hidden,
.cat-row.is-hidden { display:none; }
</style>');

layout_sidebar('categorias.php');
?>
<div class="main">
<?php layout_topbar('Categorias', []); ?>
<div class="content">
<?php layout_flash(); ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
  <div class="card">
    <div class="card-header">
      <h3><?= count($allCats) ?> categoria<?= count($allCats) !== 1 ? 's' : '' ?></h3>
    </div>
    <?php if (empty($allCats)): ?>
      <div style="padding:32px;text-align:center;color:var(--gray-m);">No hay categorias cargadas.</div>
    <?php else: ?>
      <div class="cat-toolbar">
        <label class="cat-search" aria-label="Buscar categoria">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
          <input type="search" id="categoryFilterInput" placeholder="Buscar por nombre o slug...">
        </label>
        <span class="cat-toolbar-note">Se muestran solo las categorias principales hasta abrirlas.</span>
      </div>
      <ul class="cat-tree">
        <?php foreach ($parents as $parent): ?>
          <?php
            $parentChildren = $children[$parent['id']] ?? [];
            $isOpen = $editCat && (((int)$editCat['id'] === (int)$parent['id']) || ((int)($editCat['parent_id'] ?? 0) === (int)$parent['id']));
          ?>
          <li class="cat-group <?= $isOpen ? 'is-open' : '' ?>" data-category-group data-search="<?= h(mb_strtolower($parent['name'] . ' ' . $parent['slug'])) ?>">
            <div class="cat-row is-parent" data-category-toggle-row role="button" tabindex="0" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
              <button type="button" class="cat-toggle" data-category-toggle aria-label="Mostrar subcategorias de <?= h($parent['name']) ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"></polyline></svg>
              </button>
              <div class="cat-visual">
                <span class="cat-icon"><?= h(strtoupper(substr((string)$parent['name'], 0, 2))) ?></span>
                <div>
                  <span class="cat-name"><?= h($parent['name']) ?></span>
                  <?php if (!empty($parent['description'])): ?>
                    <span class="cat-desc"><?= h($parent['description']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <span class="cat-slug"><?= h($parent['slug']) ?></span>
              <div class="cat-meta">
                <?php if ((int)$parent['prod_count'] > 0): ?>
                  <span class="cat-badge"><?= (int)$parent['prod_count'] ?> prod.</span>
                <?php endif; ?>
              </div>
              <div class="cat-actions">
                <a href="?edit=<?= (int)$parent['id'] ?>" class="btn btn-outline btn-icon btn-sm" title="Editar">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
                <form method="POST" onsubmit="return confirm('Eliminar &quot;<?= h(addslashes($parent['name'])) ?>&quot;?')">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="del_id" value="<?= (int)$parent['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-icon btn-sm" title="Eliminar">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  </button>
                </form>
              </div>
            </div>

            <div class="cat-group-children" data-category-children>
              <?php foreach ($parentChildren as $child): ?>
                <div class="cat-row is-child" data-category-child data-search="<?= h(mb_strtolower($child['name'] . ' ' . $child['slug'] . ' ' . $parent['name'])) ?>">
                  <div class="cat-visual">
                    <span class="cat-icon"><?= h(strtoupper(substr((string)$child['name'], 0, 2))) ?></span>
                    <div>
                      <span class="cat-name"><?= h($child['name']) ?></span>
                      <?php if (!empty($child['description'])): ?>
                        <span class="cat-desc"><?= h($child['description']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <span class="cat-slug"><?= h($child['slug']) ?></span>
                  <div class="cat-meta">
                    <?php if ((int)$child['prod_count'] > 0): ?>
                      <span class="cat-badge"><?= (int)$child['prod_count'] ?> prod.</span>
                    <?php endif; ?>
                  </div>
                  <div class="cat-actions">
                    <a href="?edit=<?= (int)$child['id'] ?>" class="btn btn-outline btn-icon btn-sm" title="Editar">
                      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <form method="POST" onsubmit="return confirm('Eliminar &quot;<?= h(addslashes($child['name'])) ?>&quot;?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="del_id" value="<?= (int)$child['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-icon btn-sm" title="Eliminar">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="cat-empty-filter" id="categoryFilterEmpty">No encontramos categorias o subcategorias con esa busqueda.</div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-header">
        <h3><?= $editCat ? 'Editar categoria' : 'Nueva categoria' ?></h3>
        <?php if ($editCat): ?>
          <a href="categorias.php" class="btn btn-outline btn-sm">Cancelar</a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="<?= $editCat ? 'edit' : 'create' ?>">
          <?php if ($editCat): ?>
            <input type="hidden" name="edit_id" value="<?= (int)$editCat['id'] ?>">
          <?php endif; ?>

          <div class="form-group" style="margin-bottom:16px;">
            <label>Nombre <span class="req">*</span></label>
            <input type="text" name="name" value="<?= h($editCat['name'] ?? '') ?>" required placeholder="Ej: Dormitorio, Living..." autofocus>
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label>Referencia visual</label>
            <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif">
            <span class="form-hint">Se usa como fondo en la cabecera de categoria y en la home. Si no cargás una, quedan las iniciales.</span>
            <?php if (!empty($editCat['cover_path'])): ?>
              <div style="margin-top:10px;border-radius:10px;overflow:hidden;border:1px solid #e3dbcf;">
                <img src="/montepio/<?= h($editCat['cover_path']) ?>" alt="" style="display:block;width:100%;height:120px;object-fit:cover;">
              </div>
            <?php endif; ?>
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label>Descripcion</label>
            <textarea name="description" rows="4" placeholder="Texto corto para submenu y listados..."><?= h($editCat['description'] ?? '') ?></textarea>
            <span class="form-hint">Se usa en el submenu del front y puede reutilizarse en listados.</span>
          </div>

          <div class="form-group" style="margin-bottom:20px;">
            <label>Categoria principal</label>
            <select name="parent_id">
              <option value="">- Crear como categoria principal -</option>
              <?php foreach ($parents as $parent): ?>
                <?php if ($editCat && (int)$parent['id'] === (int)$editCat['id']) continue; ?>
                <option value="<?= (int)$parent['id'] ?>" <?= ($editCat['parent_id'] ?? null) == $parent['id'] ? 'selected' : '' ?>>
                  <?= h($parent['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Si elegis una categoria principal, esta queda creada como subcategoria. Si lo dejas vacio, se crea como categoria principal.</span>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
            <?= $editCat ? 'Guardar cambios' : 'Crear categoria' ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const groups = Array.from(document.querySelectorAll('[data-category-group]'));
  if (!groups.length) return;

  const input = document.getElementById('categoryFilterInput');
  const empty = document.getElementById('categoryFilterEmpty');

  const setGroupOpen = (group, open) => {
    group.classList.toggle('is-open', open);
    const row = group.querySelector('[data-category-toggle-row]');
    if (row) row.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  groups.forEach((group) => {
    const row = group.querySelector('[data-category-toggle-row]');
    const toggle = group.querySelector('[data-category-toggle]');
    const activate = () => setGroupOpen(group, !group.classList.contains('is-open'));

    toggle?.addEventListener('click', function (event) {
      event.stopPropagation();
      activate();
    });

    row?.addEventListener('click', function (event) {
      if (event.target.closest('.cat-actions')) return;
      if (event.target.closest('a, button, form, input')) return;
      activate();
    });

    row?.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      activate();
    });
  });

  const applyFilter = () => {
    const query = (input?.value || '').trim().toLowerCase();
    let visibleGroups = 0;

    groups.forEach((group) => {
      const parentMatch = (group.dataset.search || '').includes(query);
      const children = Array.from(group.querySelectorAll('[data-category-child]'));
      let childMatchCount = 0;

      children.forEach((child) => {
        const matches = query === '' || (child.dataset.search || '').includes(query);
        child.classList.toggle('is-hidden', !matches);
        if (matches) childMatchCount += 1;
      });

      const showGroup = query === '' || parentMatch || childMatchCount > 0;
      group.classList.toggle('is-hidden', !showGroup);

      if (showGroup) {
        visibleGroups += 1;
      }

      if (query !== '') {
        setGroupOpen(group, parentMatch || childMatchCount > 0);
      }
    });

    if (empty) {
      empty.style.display = visibleGroups === 0 ? 'block' : 'none';
    }
  };

  input?.addEventListener('input', applyFilter);
  applyFilter();
});
</script>
<?php layout_foot(); ?>
