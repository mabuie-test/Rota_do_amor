<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-chart-simple"></i></span>Dashboard Administrativo</h3>
    <p class="rd-page-header__subtitle">Visão rápida dos principais indicadores de operação administrativa.</p>
  </div>
</div>
<div class="row g-3">
<?php foreach (($metrics ?? []) as $k => $v): ?>
  <div class="col-md-4 col-xl-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted"><i class="fa-solid fa-circle-nodes me-1"></i><?= e((string) $k) ?></div><div class="value"><?= e((string) $v) ?></div></div></div></div>
<?php endforeach; ?>
</div>
