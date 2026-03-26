<h3 class="mb-3">Pagamentos</h3>
<div class="rd-card"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
<thead><tr><th>ID</th><th>Utilizador</th><th>Tipo</th><th>Valor</th><th>Status</th><th>Referência</th></tr></thead>
<tbody>
<?php foreach (($payments ?? []) as $p): ?>
<tr><td><?= (int)$p['id'] ?></td><td>#<?= (int)$p['user_id'] ?></td><td><?= e($p['payment_type']) ?></td><td><?= e($p['amount']) ?> <?= e($p['currency']) ?></td><td><?php $kind=$p['status']==='completed'?'paid':($p['status']==='failed'?'failed':'pending'); $label=$p['status']; require dirname(__DIR__).'/partials/badge.php'; ?></td><td><?= e((string)($p['debito_reference']??'-')) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
