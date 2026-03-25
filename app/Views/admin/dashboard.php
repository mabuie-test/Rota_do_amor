<h3 class="mb-3">Dashboard Administrativo</h3>
<div class="row g-3">
<?php foreach (($metrics ?? []) as $k => $v): ?>
  <div class="col-md-4 col-xl-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted"><?= e((string) $k) ?></div><div class="value"><?= e((string) $v) ?></div></div></div></div>
<?php endforeach; ?>
</div>
