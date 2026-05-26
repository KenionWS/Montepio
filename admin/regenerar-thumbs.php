<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/product_thumbs.php';

auth_require();

$runResult = null;
$productIdValue = '';
$batchValue = (string)($_GET['batch'] ?? '50');
$afterIdValue = (string)($_GET['after_id'] ?? '0');
$shouldRun = isset($_GET['run']) && $_GET['run'] === '1';

$productIdValue = trim((string)($_GET['product_id'] ?? ''));
$productId = $productIdValue !== '' ? (int)$productIdValue : null;
$batchSize = (int)$batchValue;
$afterId = (int)$afterIdValue;

if ($productId !== null && $productId <= 0) {
    $productId = null;
    $productIdValue = '';
}

if ($batchSize <= 0) {
    $batchSize = 50;
}
$batchSize = max(1, min(500, $batchSize));
$batchValue = (string)$batchSize;

if ($afterId < 0) {
    $afterId = 0;
}
$afterIdValue = (string)$afterId;

if ($shouldRun) {
    $runResult = regenerate_product_thumbs_batch($productId, $afterId, $batchSize);
}

function regen_thumbs_url(array $params = []): string
{
    $base = ADMIN_URL . '/regenerar-thumbs.php';
    $query = array_filter($params, static function ($value): bool {
        return $value !== null && $value !== '';
    });

    return $base . ($query ? '?' . http_build_query($query) : '');
}

layout_head('Regenerar thumbs', '<style>
.tools-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.9fr);gap:20px;align-items:start}
.tool-card p{color:var(--gray-m);font-size:13px;line-height:1.6}
.tool-card form{display:flex;flex-direction:column;gap:14px}
.tool-result{margin-top:18px;border-top:1px solid #ece7dd;padding-top:18px}
.tool-result-list{display:flex;flex-direction:column;gap:8px;max-height:360px;overflow:auto;padding-right:6px}
.tool-log{padding:10px 12px;border-radius:8px;font-size:12px;line-height:1.5}
.tool-log.ok{background:#e8f5ef;color:var(--green)}
.tool-log.error{background:var(--red-l);color:var(--red)}
.tool-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:14px}
.tool-summary .kpi{padding:16px 18px}
.tool-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.tool-inline-note{margin-top:10px;font-size:12px;color:var(--gray-m)}
.tool-url-box{margin-top:10px;padding:10px 12px;border:1px solid #ece7dd;border-radius:8px;background:var(--gray-l);font-size:12px;line-height:1.5;word-break:break-all}
@media(max-width:980px){.tools-grid{grid-template-columns:1fr}.tool-summary{grid-template-columns:1fr}}
</style>');
layout_sidebar('regenerar-thumbs.php');
?>
<div class="main">
<?php layout_topbar('Regenerar thumbs', [
    ['href' => ADMIN_URL . '/dashboard.php', 'label' => '<- Dashboard', 'class' => 'btn btn-outline btn-sm'],
]); ?>
<div class="content">
<?php layout_flash(); ?>

<div class="tools-grid">
  <div class="card tool-card">
    <div class="card-header">
      <h3>Miniaturas de productos 300x300</h3>
    </div>
    <div class="card-body">
      <p>Esta herramienta vuelve a generar las thumbs cuadradas por lotes para que muestren la imagen completa, sin recorte. El proceso usa `batch` y `after_id` en la URL para avanzar sin depender de una sola ejecución larga.</p>
      <form method="GET">
        <div class="form-group">
          <label for="product_id">ID de producto (opcional)</label>
          <input type="number" min="1" step="1" id="product_id" name="product_id" value="<?= h($productIdValue) ?>" placeholder="Ej: 123">
          <span class="form-hint">Si lo completas, limita la regeneración a un solo producto.</span>
        </div>
        <div class="form-group">
          <label for="batch">Tamaño del lote</label>
          <input type="number" min="1" max="500" step="1" id="batch" name="batch" value="<?= h($batchValue) ?>" placeholder="50">
          <span class="form-hint">Cantidad de imágenes a procesar por ejecución.</span>
        </div>
        <div class="form-group">
          <label for="after_id">Empezar después del ID</label>
          <input type="number" min="0" step="1" id="after_id" name="after_id" value="<?= h($afterIdValue) ?>" placeholder="0">
          <span class="form-hint">Usa `0` para arrancar desde el principio. Después podés seguir con el `last_id` del lote anterior.</span>
        </div>
        <div class="d-flex ai-center gap-10">
          <input type="hidden" name="run" value="1">
          <button type="submit" class="btn btn-primary">Ejecutar lote</button>
          <a href="<?= h(regen_thumbs_url()) ?>" class="btn btn-outline">Limpiar</a>
        </div>
      </form>
      <div class="tool-url-box"><?= h(regen_thumbs_url(['run' => 1, 'batch' => $batchSize, 'after_id' => $afterId, 'product_id' => $productIdValue !== '' ? $productIdValue : null])) ?></div>
    </div>
  </div>

  <div class="card tool-card">
    <div class="card-header">
      <h3>Como usarlo</h3>
    </div>
    <div class="card-body">
      <p>1. Arrancá con `after_id=0` y un `batch` chico, por ejemplo `25` o `50`.</p>
      <p>2. Al terminar, usá el botón de siguiente lote o copiá el `last_id` y pasalo como nuevo `after_id`.</p>
      <p>3. Repetí hasta que `queden 0` pendientes.</p>
      <p>La herramienta usa la imagen full o medium existente como fuente. Si alguna imagen no tiene archivo disponible, se informa en el resultado.</p>
    </div>
  </div>
</div>

<?php if ($runResult !== null): ?>
  <div class="tool-result">
    <div class="tool-summary">
      <div class="kpi kpi-green"><div class="kpi-val"><?= (int)$runResult['processed'] ?></div><div class="kpi-label">Regeneradas</div></div>
      <div class="kpi"><div class="kpi-val"><?= (int)$runResult['remaining_before'] ?></div><div class="kpi-label">Pendientes al iniciar</div></div>
      <div class="kpi kpi-red"><div class="kpi-val"><?= (int)$runResult['failed'] ?></div><div class="kpi-label">Con error</div></div>
    </div>
    <div class="card">
      <div class="card-header">
        <h3>Detalle de ejecucion</h3>
      </div>
      <div class="card-body">
        <p class="tool-inline-note">
          Total del filtro: <?= (int)$runResult['total'] ?> imagen(es).
          Lote ejecutado: hasta ID <?= (int)$runResult['last_id'] ?>.
          Quedan luego de este lote: <?= product_thumb_remaining_count($productId, (int)$runResult['last_id']) ?>.
        </p>
        <div class="tool-actions">
          <?php if (!empty($runResult['has_more'])): ?>
            <a class="btn btn-primary" href="<?= h(regen_thumbs_url([
                'run' => 1,
                'batch' => $batchSize,
                'after_id' => (int)$runResult['last_id'],
                'product_id' => $productIdValue !== '' ? $productIdValue : null,
            ])) ?>">Siguiente lote</a>
          <?php endif; ?>
          <a class="btn btn-outline" href="<?= h(regen_thumbs_url([
              'batch' => $batchSize,
              'after_id' => (int)$runResult['last_id'],
              'product_id' => $productIdValue !== '' ? $productIdValue : null,
          ])) ?>">Dejar preparado el próximo</a>
        </div>
        <div class="tool-url-box"><?= h(regen_thumbs_url([
            'run' => 1,
            'batch' => $batchSize,
            'after_id' => (int)$runResult['last_id'],
            'product_id' => $productIdValue !== '' ? $productIdValue : null,
        ])) ?></div>
        <div class="tool-result-list">
          <?php foreach (($runResult['logs'] ?? []) as $log): ?>
            <div class="tool-log <?= $log['type'] === 'ok' ? 'ok' : 'error' ?>"><?= h((string)$log['message']) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

</div>
</div>
<?php layout_foot(); ?>
