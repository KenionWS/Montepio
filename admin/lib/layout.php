<?php
declare(strict_types=1);

function layout_head(string $title, string $extraHead = ''): void { ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> — Montepio Admin</title>
<link rel="icon" type="image/x-icon" href="/Montepio/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#004d33;--green-dark:#003324;--green-mid:#005c3d;--green-light:#e8f5ef;
  --gold:#c9a84c;--cream:#faf8f4;--white:#ffffff;
  --gray-l:#f2efe9;--gray-m:#9e9890;--gray-d:#3a3630;--text:#2b2820;
  --red:#c0392b;--red-l:#fdf2f2;--orange:#d97706;--orange-l:#fffbeb;
  --sidebar:240px;
}
html{font-size:14px;height:100%}
body{font-family:'Inter',sans-serif;background:var(--cream);color:var(--text);display:flex;min-height:100vh}

/* ── Sidebar ── */
.sidebar{
  width:var(--sidebar);min-height:100vh;background:var(--green);
  display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50;
}
.sidebar-logo{
  padding:24px 20px;border-bottom:1px solid rgba(255,255,255,.1);
  display:flex;align-items:center;gap:10px;text-decoration:none;
}
.sidebar-logo-image{display:block;height:auto;width:100%;flex-shrink:0;}

.sidebar-logo-text .sub{font-size:10px;color:rgba(255,255,255,.5);letter-spacing:.1em;text-transform:uppercase;}
.sidebar-nav{flex:1;padding:12px 0;overflow-y:auto;}
.nav-section{padding:16px 16px 6px;font-size:10px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.35);}
.nav-link{
  display:flex;align-items:center;gap:10px;padding:10px 20px;
  color:rgba(255,255,255,.72);text-decoration:none;font-size:13px;font-weight:500;
  transition:background .15s,color .15s;position:relative;
}
.nav-link:hover{background:rgba(255,255,255,.08);color:white;}
.nav-link.active{background:rgba(255,255,255,.12);color:white;}
.nav-link.active::before{content:'';position:absolute;left:0;top:4px;bottom:4px;width:3px;background:var(--gold);border-radius:0 2px 2px 0;}
.nav-link svg{flex-shrink:0;opacity:.7;}
.nav-link.active svg,.nav-link:hover svg{opacity:1;}
.nav-badge{margin-left:auto;background:rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:10px;font-weight:600;padding:2px 7px;border-radius:10px;}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
.sidebar-footer a{color:rgba(255,255,255,.5);text-decoration:none;font-size:12px;display:flex;align-items:center;gap:6px;transition:color .2s;}
.sidebar-footer a:hover{color:white;}

/* ── Main ── */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{
  background:var(--white);border-bottom:1px solid #ece7dd;
  padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:40;
}
.topbar-title{font-size:15px;font-weight:600;color:var(--text);}
.topbar-actions{display:flex;align-items:center;gap:10px;}
.content{padding:28px 32px;flex:1;}

/* ── Cards ── */
.card{background:var(--white);border:1px solid #ece7dd;border-radius:12px;overflow:hidden;}
.card-header{padding:16px 20px;border-bottom:1px solid #ece7dd;display:flex;align-items:center;justify-content:space-between;gap:12px;}
.card-header h3{font-size:14px;font-weight:600;color:var(--text);}
.card-body{padding:20px;}

/* ── KPI cards ── */
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.kpi{background:var(--white);border:1px solid #ece7dd;border-radius:12px;padding:20px 22px;}
.kpi-val{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:var(--text);line-height:1;}
.kpi-label{font-size:12px;color:var(--gray-m);margin-top:6px;}
.kpi-green .kpi-val{color:var(--green);}
.kpi-gold .kpi-val{color:var(--gold);}
.kpi-red .kpi-val{color:var(--red);}

/* ── Tabla ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th{padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--gray-m);background:var(--gray-l);border-bottom:1px solid #ece7dd;}
td{padding:12px 14px;border-bottom:1px solid #f0ebe3;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#faf9f7;}
.td-thumb{width:48px;height:48px;object-fit:cover;border-radius:6px;background:var(--gray-l);}
.td-thumb-placeholder{width:48px;height:48px;border-radius:6px;background:var(--gray-l);display:flex;align-items:center;justify-content:center;color:var(--gray-m);font-size:18px;}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:100px;font-size:11px;font-weight:600;}
.badge-green{background:#e8f5ef;color:var(--green);}
.badge-gold{background:#fff8e8;color:#8a6500;}
.badge-red{background:var(--red-l);color:var(--red);}
.badge-gray{background:var(--gray-l);color:var(--gray-d);}
.badge-dot{width:6px;height:6px;border-radius:50%;background:currentColor;}

/* ── Botones ── */
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:background .2s,transform .1s;}
.btn:hover{transform:translateY(-1px);}
.btn:active{transform:translateY(0);}
.btn-primary{background:var(--green);color:white;}
.btn-primary:hover{background:var(--green-mid);}
.btn-gold{background:var(--gold);color:white;}
.btn-gold:hover{background:#b8933e;}
.btn-danger{background:var(--red);color:white;}
.btn-danger:hover{background:#a93226;}
.btn-outline{background:transparent;color:var(--text);border:1.5px solid #d8d2c9;}
.btn-outline:hover{background:var(--gray-l);border-color:#c0bab0;}
.btn-sm{padding:5px 11px;font-size:12px;}
.btn-icon{width:32px;height:32px;padding:0;justify-content:center;}

/* ── Formularios ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.form-full{grid-column:1/-1;}
.form-group{display:flex;flex-direction:column;gap:5px;}
label{font-size:12px;font-weight:600;color:var(--gray-d);}
label .req{color:var(--red);}
input[type=text],input[type=number],input[type=password],select,textarea{
  width:100%;padding:9px 12px;border:1px solid #d8d2c9;border-radius:7px;
  font-size:13px;font-family:'Inter',sans-serif;color:var(--text);background:white;outline:none;
  transition:border-color .2s;
}
input:focus,select:focus,textarea:focus{border-color:var(--green);}
textarea{resize:vertical;min-height:100px;}
.form-hint{font-size:11px;color:var(--gray-m);margin-top:2px;}
.radio-group{display:flex;gap:10px;flex-wrap:wrap;}
.radio-opt{display:flex;align-items:center;gap:7px;padding:8px 14px;border:1.5px solid #d8d2c9;border-radius:7px;cursor:pointer;transition:border-color .2s,background .2s;}
.radio-opt input{display:none;}
.radio-opt.selected,.radio-opt:has(input:checked){border-color:var(--green);background:var(--green-light);}
.radio-dot{width:12px;height:12px;border-radius:50%;border:2px solid var(--gray-m);transition:all .15s;}
.radio-opt.selected .radio-dot,.radio-opt:has(input:checked) .radio-dot{border-color:var(--green);background:var(--green);}
.radio-label{font-size:13px;font-weight:500;}

/* ── Flash ── */
.flash{padding:12px 18px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.flash-ok{background:#e8f5ef;color:var(--green);border:1px solid #b8dfc9;}
.flash-err{background:var(--red-l);color:var(--red);border:1px solid #f5c6c0;}

/* ── Upload zone ── */
.drop-zone{
  border:2px dashed #d8d2c9;border-radius:12px;padding:48px 24px;
  text-align:center;cursor:pointer;transition:border-color .2s,background .2s;
  background:var(--gray-l);
}
.drop-zone.dragover{border-color:var(--green);background:var(--green-light);}
.drop-zone .dz-icon{font-size:48px;margin-bottom:12px;}
.drop-zone h3{font-size:16px;font-weight:600;color:var(--text);margin-bottom:6px;}
.drop-zone p{font-size:13px;color:var(--gray-m);}

/* ── Product cards (bulk) ── */
.bulk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:24px;}
.bulk-card{background:var(--white);border:1px solid #ece7dd;border-radius:10px;overflow:hidden;}
.bulk-card-img{width:100%;aspect-ratio:1;background:var(--gray-l);position:relative;overflow:hidden;}
.bulk-card-img img{width:100%;height:100%;object-fit:cover;}
.bulk-card-img .img-overlay{position:absolute;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;}
.bulk-card:hover .img-overlay{opacity:1;}
.bulk-card-body{padding:14px;}
.bulk-card-body input,.bulk-card-body select{margin-bottom:8px;font-size:12px;padding:7px 10px;}
.bulk-card.loading .bulk-card-img::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,.7);}
.progress-bar{width:100%;height:3px;background:#e0dbd2;border-radius:2px;overflow:hidden;margin-top:6px;}
.progress-fill{height:100%;background:var(--green);transition:width .3s;border-radius:2px;}

/* ── Image previews (single product) ── */
.img-previews{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;}
.img-preview-item{position:relative;width:90px;height:90px;border-radius:8px;overflow:hidden;border:1px solid #ece7dd;}
.img-preview-item img{width:100%;height:100%;object-fit:cover;}
.img-preview-item .del-btn{position:absolute;top:4px;right:4px;background:rgba(192,57,43,.85);color:white;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;}

/* ── Misc ── */
.sep{height:1px;background:#ece7dd;margin:20px 0;}
.text-m{color:var(--gray-m);}
.text-sm{font-size:12px;}
.fw-600{font-weight:600;}
.d-flex{display:flex;}
.ai-center{align-items:center;}
.gap-10{gap:10px;}
.mt-auto{margin-top:auto;}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:white;border-radius:50%;animation:spin .6s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

@media(max-width:900px){
  .sidebar{transform:translateX(-100%);}
  .main{margin-left:0;}
  .kpi-grid{grid-template-columns:repeat(2,1fr);}
  .form-grid{grid-template-columns:1fr;}
}
</style>
<?= $extraHead ?>
</head>
<body>
<?php }

function layout_sidebar(string $active = ''): void
{
    $links = [
        ['href' => 'dashboard.php',    'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>', 'label' => 'Dashboard'],
        ['href' => 'productos.php',    'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>', 'label' => 'Productos'],
        ['href' => 'producto.php',     'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>', 'label' => 'Nuevo producto'],
        ['href' => 'carga-masiva.php', 'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>', 'label' => 'Carga masiva'],
        ['href' => 'categorias.php',   'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>', 'label' => 'Categorías'],
        ['href' => 'home.php',         'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/></svg>', 'label' => 'Home'],
        ['href' => 'about.php',        'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 10h.01M15 10h.01"/></svg>', 'label' => 'Quienes somos'],
        ['href' => 'popup.php',        'icon' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 13h5"/></svg>', 'label' => 'Popup'],
    ];
    ?>
<aside class="sidebar">
  <a href="dashboard.php" class="sidebar-logo">
    <img src="/Montepio/assets/brand/montepio-logo-wt.png" alt="Montepio Antiguedades" class="sidebar-logo-image"> <div class="sidebar-logo-text">
    </div>
  </a>
  <nav class="sidebar-nav">
    <div class="nav-section">Gestión</div>
    <?php foreach ($links as $l): ?>
    <a href="<?= ADMIN_URL ?>/<?= $l['href'] ?>"
       class="nav-link <?= $active === $l['href'] ? 'active' : '' ?>">
      <?= $l['icon'] ?>
      <?= h($l['label']) ?>
    </a>
    <?php endforeach; ?>
    <div class="nav-section" style="margin-top:12px;">Sitio</div>
    <a href="/Montepio/" target="_blank" class="nav-link">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      Ver el sitio
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= ADMIN_URL ?>/logout.php">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Cerrar sesión
    </a>
  </div>
</aside>
<?php }

function layout_topbar(string $title, array $actions = []): void { ?>
<div class="topbar">
  <span class="topbar-title"><?= h($title) ?></span>
  <div class="topbar-actions">
    <?php foreach ($actions as $a): ?>
      <a href="<?= h($a['href']) ?>" class="btn <?= $a['class'] ?? 'btn-primary' ?>">
        <?= $a['icon'] ?? '' ?> <?= h($a['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php }

function layout_flash(): void
{
    $f = flash_get();
    if (!$f) return;
    $cls = $f['type'] === 'ok' ? 'flash-ok' : 'flash-err';
    echo '<div class="flash ' . $cls . '">' . h($f['msg']) . '</div>';
}

function layout_foot(): void { ?>
</body></html>
<?php }
