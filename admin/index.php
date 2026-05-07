<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';

// Ya logueado → al dashboard
if (!empty($_SESSION['admin_ok'])) {
    header('Location: ' . ADMIN_URL . '/dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (auth_login($_POST['password'] ?? '')) {
        header('Location: ' . ADMIN_URL . '/dashboard.php'); exit;
    }
    $error = 'Contraseña incorrecta.';
    sleep(1); // throttle brute force
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Montepio Admin</title>
<link rel="icon" type="image/x-icon" href="/Montepio/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#003324 0%,#005c3d 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.box{background:white;border-radius:16px;padding:44px 40px;width:100%;max-width:400px;box-shadow:0 16px 48px rgba(0,0,0,.25);}
.logo{display:flex;align-items:center;gap:14px;margin-bottom:32px;}
.logo-image{display:block;height:58px;width:auto;}

.logo-text .sub{font-size:10px;color:#9e9890;letter-spacing:.18em;text-transform:uppercase;}
h1{font-size:22px;font-weight:600;color:#2b2820;margin-bottom:6px;}
p{font-size:13px;color:#9e9890;margin-bottom:28px;}
label{display:block;font-size:12px;font-weight:600;color:#3a3630;margin-bottom:6px;}
.input-wrap{position:relative;margin-bottom:20px;}
input[type=password]{width:100%;padding:11px 14px;border:1.5px solid #d8d2c9;border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;}
input:focus{border-color:#004d33;}
.btn{width:100%;padding:13px;background:#004d33;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s;}
.btn:hover{background:#005c3d;}
.error{background:#fdf2f2;color:#c0392b;border:1px solid #f5c6c0;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
.site-link{display:block;text-align:center;margin-top:20px;font-size:12px;color:#9e9890;text-decoration:none;transition:color .2s;}
.site-link:hover{color:#004d33;}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <img src="/Montepio/assets/brand/montepio-logo.jpg" alt="Montepio Antiguedades" class="logo-image"><div class="logo-text"><div class="sub">Admin</div></div>
  </div>
  <h1>Iniciar sesión</h1>
  <p>Ingresá la contraseña para acceder al panel.</p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_input() ?>
    <label for="password">Contraseña</label>
    <div class="input-wrap">
      <input type="password" id="password" name="password" autofocus required placeholder="••••••••">
    </div>
    <button type="submit" class="btn">Entrar al panel</button>
  </form>
  <a href="/Montepio/" class="site-link">← Ver el sitio</a>
</div>
</body>
</html>

