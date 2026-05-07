<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';

// Si ya está configurado, solo puede acceder si no hay sesión de admin activa
if (session_status() === PHP_SESSION_NONE) session_start();
if (ADMIN_PASS_HASH !== '' && empty($_SESSION['admin_ok'])) {
    header('Location: ' . ADMIN_URL . '/index.php'); exit;
}

$error = '';
$done  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass    = $_POST['password']   ?? '';
    $confirm = $_POST['confirm']    ?? '';

    if (strlen($pass) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($pass !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash    = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $content = "<?php return ['hash' => " . var_export($hash, true) . "];";
        if (file_put_contents(PASS_FILE, $content) !== false) {
            $done = true;
        } else {
            $error = 'No se pudo escribir en ' . PASS_FILE . '. Verificá permisos del directorio data/.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuración inicial — Montepio Admin</title>
<link rel="icon" type="image/x-icon" href="/montepio/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f2efe9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.box{background:white;border-radius:14px;padding:40px;width:100%;max-width:420px;box-shadow:0 4px 24px rgba(0,0,0,.08);}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
.logo-image{display:block;height:56px;width:auto;}

.logo-text .sub{font-size:10px;color:#9e9890;letter-spacing:.15em;text-transform:uppercase;}
h1{font-size:20px;font-weight:600;color:#2b2820;margin-bottom:6px;}
p{font-size:13px;color:#9e9890;margin-bottom:24px;line-height:1.6;}
label{display:block;font-size:12px;font-weight:600;color:#3a3630;margin-bottom:5px;}
input{width:100%;padding:10px 12px;border:1px solid #d8d2c9;border-radius:7px;font-size:13px;font-family:'Inter',sans-serif;outline:none;margin-bottom:14px;transition:border-color .2s;}
input:focus{border-color:#004d33;}
.btn{width:100%;padding:12px;background:#004d33;color:white;border:none;border-radius:7px;font-size:14px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s;}
.btn:hover{background:#005c3d;}
.error{background:#fdf2f2;color:#c0392b;border:1px solid #f5c6c0;padding:10px 14px;border-radius:7px;font-size:13px;margin-bottom:16px;}
.success{background:#e8f5ef;color:#004d33;border:1px solid #b8dfc9;padding:16px;border-radius:7px;font-size:13px;text-align:center;}
.success a{color:#004d33;font-weight:600;}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <img src="/montepio/assets/brand/montepio-logo.jpg" alt="Montepio Antiguedades" class="logo-image"><div class="logo-text"><div class="sub">Configuracion inicial</div></div>
  </div>

  <?php if ($done): ?>
    <div class="success">
      ✓ Contraseña configurada correctamente.<br><br>
      <a href="<?= ADMIN_URL ?>/index.php">→ Ir al login</a>
    </div>
  <?php else: ?>
    <h1>Crear contraseña de acceso</h1>
    <p>Esta pantalla aparece solo la primera vez. Elegí una contraseña para el panel de administración.</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label>Nueva contraseña</label>
      <input type="password" name="password" placeholder="Mínimo 8 caracteres" autofocus required>
      <label>Confirmar contraseña</label>
      <input type="password" name="confirm" placeholder="Repetí la contraseña" required>
      <button type="submit" class="btn">Guardar contraseña</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>

