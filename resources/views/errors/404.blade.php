<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pagina no encontrada | Montepio Antiguedades</title>
  <link rel="icon" type="image/x-icon" href="{{ $baseUrl ?? '/New' }}/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ $baseUrl ?? '/New' }}/assets/site/shared.css">
  <link rel="stylesheet" href="{{ $baseUrl ?? '/New' }}/assets/site/error.css">
</head>
<body class="error-page">
  <div class="topbar">
    <div class="topbar-inner">
      <div class="topbar-group">
        <span class="topbar-item">Montepio Antiguedades</span>
      </div>
      <div class="topbar-group">
        <span class="topbar-item">Av. Rivadavia 7701, CABA</span>
      </div>
    </div>
  </div>

  <header class="site-header">
    <div class="header-inner">
      <a href="{{ url('/') }}" class="site-logo">
        <img src="{{ $baseUrl ?? '/New' }}/assets/brand/montepio-logo.jpg" alt="Montepio Antiguedades" class="site-logo-image">
      </a>
    </div>
  </header>

  <main class="error-shell">
    <section class="error-card">
      <div class="error-eyebrow">Error 404</div>
      <h1 class="error-title">Esta pagina no existe</h1>
      <p class="error-copy">La URL que intentaste abrir no esta disponible, fue removida o todavia no fue publicada. Podes volver al inicio o seguir navegando el catalogo.</p>
      <div class="error-actions">
        <a href="{{ url('/') }}" class="btn-primary">Ir al inicio</a>
        <a href="{{ url('/catalogo') }}" class="btn-secondary">Ver catalogo</a>
      </div>
    </section>
  </main>

  <footer class="site-footer" style="margin-top:0;">
    <div class="site-footer-inner">
      <span class="site-footer-copy">© 2026 Montepio Antiguedades.</span>
      <a href="{{ url('/') }}" class="site-footer-link">Volver al inicio</a>
    </div>
  </footer>
</body>
</html>
