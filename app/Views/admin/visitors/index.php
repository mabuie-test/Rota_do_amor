<?php $items = $items ?? []; $overview = $overview ?? []; $filters = $filters ?? []; $pagination = $pagination ?? ['page'=>1,'total_pages'=>1,'total'=>0,'per_page'=>25]; $leaders = $leaders ?? []; $policy = $premium_policy ?? []; ?>
<h3 class="mb-3">Radar de Visitantes · Administração</h3>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Visitas (janela)</div><div class="value"><?= (int) ($overview['visits_total'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Repetição</div><div class="value"><?= e((string) ($overview['repeat_rate_percent'] ?? 0)) ?>%</div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Sinais artificiais</div><div class="value"><?= (int) ($overview['suspicious_visitors'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card"><div class="card-body small"><strong>Premium policy</strong><br>Free visível: <?= (int) ($policy['free_visible_visitors'] ?? 2) ?><br>Histórico free/premium: <?= (int) ($policy['free_history_hours'] ?? 24) ?>h / <?= (int) ($policy['premium_history_days'] ?? 30) ?>d</div></div></div>
</div>
<div class="rd-card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get">
<div class="col-md-2"><label class="form-label mb-1">Visitor ID</label><input class="form-control form-control-sm" name="visitor_user_id" value="<?= e((string) ($filters['visitor_user_id'] ?? '')) ?>"></div>
<div class="col-md-2"><label class="form-label mb-1">Visited ID</label><input class="form-control form-control-sm" name="visited_user_id" value="<?= e((string) ($filters['visited_user_id'] ?? '')) ?>"></div>
<div class="col-md-2"><label class="form-label mb-1">De</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>"></div>
<div class="col-md-2"><label class="form-label mb-1">Até</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>"></div>
<div class="col-md-2"><label class="form-label mb-1">Suspeitos</label><select class="form-select form-select-sm" name="only_suspicious"><option value="0">todos</option><option value="1" <?= (int) ($filters['only_suspicious'] ?? 0) === 1 ? 'selected' : '' ?>>somente suspeitos</option></select></div>
<div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div></form></div></div>
<div class="rd-card"><div class="card-body table-responsive"><table class="table table-modern align-middle"><thead><tr><th>ID</th><th>Par</th><th>Origem</th><th>Comportamento</th><th>Criado</th><th></th></tr></thead><tbody>
<?php if ($items === []): ?><tr><td colspan="6" class="small text-muted">Sem visitas para os filtros aplicados.</td></tr><?php endif; ?>
<?php foreach ($items as $item): ?><tr><td>#<?= (int) $item['id'] ?></td><td><a href="<?= e(url((string) ($item['links']['visitor'] ?? '/admin/users'))) ?>">#<?= (int) $item['visitor_user_id'] ?></a> → <a href="<?= e(url((string) ($item['links']['visited'] ?? '/admin/users'))) ?>">#<?= (int) $item['visited_user_id'] ?></a></td><td><?= e((string) ($item['source_context'] ?? 'discover')) ?></td><td><?= !empty($item['visitor_is_premium']) ? 'premium' : 'free' ?> · total visitante <?= (int) ($item['visitor_visits'] ?? 0) ?></td><td><?= e((string) ($item['created_at'] ?? '')) ?></td><td><a class="btn btn-sm btn-outline-secondary" href="<?= e(url((string) ($item['links']['audit'] ?? '/admin/audit'))) ?>">audit</a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
