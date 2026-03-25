<h2>Painel Administrativo</h2>
<div class="row">
<?php foreach (($metrics ?? []) as $k => $v): ?>
  <div class="col-md-3 mb-2"><div class="card"><div class="card-body"><strong><?= e((string) $k) ?></strong><br><?= e((string) $v) ?></div></div></div>
<?php endforeach; ?>
</div>
