<?php $events = $events ?? []; $filters = $filters ?? []; ?>
<h3 class="mb-3">Centro de Auditoria</h3>
<div class="rd-card mb-3"><div class="card-body">
<form class="row g-2 align-items-end" method="get">
  <div class="col-md-2"><label class="form-label mb-1">Actor</label><input class="form-control form-control-sm" name="actor_type" value="<?= e((string) ($filters['actor_type'] ?? '')) ?>" placeholder="admin/user"></div>
  <div class="col-md-2"><label class="form-label mb-1">Acção</label><input class="form-control form-control-sm" name="action" value="<?= e((string) ($filters['action'] ?? '')) ?>"></div>
  <div class="col-md-2"><label class="form-label mb-1">Alvo</label><input class="form-control form-control-sm" name="target_type" value="<?= e((string) ($filters['target_type'] ?? '')) ?>"></div>
  <div class="col-md-2"><label class="form-label mb-1">De</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>"></div>
  <div class="col-md-2"><label class="form-label mb-1">Até</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>"></div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div>
</form>
</div></div>
<div class="rd-card"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
<thead><tr><th>Quando</th><th>Quem</th><th>Acção</th><th>Alvo</th><th>Motivo/metadata</th></tr></thead>
<tbody>
<?php foreach ($events as $event): ?>
<tr>
  <td><?= e((string) $event['created_at']) ?></td>
  <td><?= e((string) ($event['admin_name'] ?? ($event['actor_type'] . '#' . $event['actor_id']))) ?></td>
  <td><?= e((string) $event['action']) ?></td>
  <td><?= e((string) ($event['target_type'] ?? '')) ?>#<?= e((string) ($event['target_id'] ?? '')) ?></td>
  <td><code class="small"><?= e((string) ($event['metadata_json'] ?? '{}')) ?></code></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
