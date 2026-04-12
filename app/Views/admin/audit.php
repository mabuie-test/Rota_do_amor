<?php $events = $events ?? []; $filters = $filters ?? []; $pagination = $pagination ?? ['page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 50]; $actions = $actions ?? []; ?>
<h3 class="mb-3">Centro de Auditoria</h3>
<div class="rd-card mb-3"><div class="card-body">
<form class="row g-2 align-items-end" method="get">
  <div class="col-md-2"><label class="form-label mb-1">Actor type</label><input class="form-control form-control-sm" name="actor_type" value="<?= e((string) ($filters['actor_type'] ?? '')) ?>" placeholder="admin/user/system"></div>
  <div class="col-md-1"><label class="form-label mb-1">Actor ID</label><input class="form-control form-control-sm" name="actor_id" value="<?= e((string) ($filters['actor_id'] ?? '')) ?>"></div>
  <div class="col-md-1"><label class="form-label mb-1">Admin ID</label><input class="form-control form-control-sm" name="admin_id" value="<?= e((string) ($filters['admin_id'] ?? '')) ?>"></div>
  <div class="col-md-2"><label class="form-label mb-1">Acção</label><select class="form-select form-select-sm" name="action"><option value="">todas</option><?php foreach ($actions as $action): ?><option value="<?= e((string) $action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>><?= e((string) $action) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label mb-1">Target type</label><input class="form-control form-control-sm" name="target_type" value="<?= e((string) ($filters['target_type'] ?? '')) ?>"></div>
  <div class="col-md-1"><label class="form-label mb-1">Target ID</label><input class="form-control form-control-sm" name="target_id" value="<?= e((string) ($filters['target_id'] ?? '')) ?>"></div>
  <div class="col-md-3"><label class="form-label mb-1">Pesquisa textual</label><input class="form-control form-control-sm" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="metadata, action, target"></div>
  <div class="col-md-2"><label class="form-label mb-1">De</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>"></div>
  <div class="col-md-2"><label class="form-label mb-1">Até</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>"></div>
  <div class="col-md-2"><label class="form-label mb-1">Por página</label><select class="form-select form-select-sm" name="per_page"><?php foreach ([50,100,200] as $size): ?><option value="<?= $size ?>" <?= (int) ($pagination['per_page'] ?? 50) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div>
</form>
</div></div>
<div class="rd-card"><div class="card-body table-responsive">
<p class="small text-muted">Total de eventos: <strong><?= (int) ($pagination['total'] ?? 0) ?></strong> · Página <?= (int) ($pagination['page'] ?? 1) ?> / <?= (int) ($pagination['total_pages'] ?? 1) ?></p>
<table class="table table-modern align-middle">
<thead><tr><th>Quando</th><th>Actor</th><th>Acção</th><th>Alvo</th><th>Origem / Motivo</th><th>Metadata</th></tr></thead>
<tbody>
<?php foreach ($events as $event): ?>
<tr>
  <td><?= e((string) $event['created_at']) ?></td>
  <td><?= e((string) ($event['actor_display'] ?? 'n/d')) ?></td>
  <td><strong><?= e((string) $event['action']) ?></strong><br><small class="text-muted"><?= e((string) ($event['actor_type'] ?? '')) ?></small></td>
  <td><?= e((string) ($event['target_display'] ?? '')) ?></td>
  <td><small>Módulo: <strong><?= e((string) ($event['source_module'] ?? 'core')) ?></strong></small><br><small><?= e((string) (($event['reason'] ?? '') !== '' ? $event['reason'] : 'sem motivo explícito')) ?></small></td>
  <td><code class="small"><?= e((string) json_encode($event['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<div class="d-flex gap-2">
  <?php $prev = max(1, ((int) ($pagination['page'] ?? 1)) - 1); $next = min((int) ($pagination['total_pages'] ?? 1), ((int) ($pagination['page'] ?? 1)) + 1); ?>
  <a class="btn btn-sm btn-outline-secondary <?= ((int) ($pagination['page'] ?? 1) <= 1) ? 'disabled' : '' ?>" href="?<?= http_build_query(array_merge($filters, ['page' => $prev])) ?>">Anterior</a>
  <a class="btn btn-sm btn-outline-secondary <?= ((int) ($pagination['page'] ?? 1) >= (int) ($pagination['total_pages'] ?? 1)) ? 'disabled' : '' ?>" href="?<?= http_build_query(array_merge($filters, ['page' => $next])) ?>">Próxima</a>
</div>
</div></div>
