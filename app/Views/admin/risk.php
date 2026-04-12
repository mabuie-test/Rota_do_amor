<?php $users = $users ?? []; $overview = $overview ?? []; $inviteAnomalies = $invites_anomalies ?? []; $messageAnomalies = $messages_anomalies ?? []; $queue = $priority_queue ?? []; $currentPriority = $current_priority ?? ''; $explainability = $explainability ?? []; ?>
<h3 class="mb-3">Centro de Risco & Abuso</h3>
<?php if (!empty($explainability)): ?><div class="alert alert-light border py-2"><strong>Modelo actual:</strong> <?= e((string) ($explainability['method'] ?? 'Score composto')) ?> · <span class="small text-muted">actualizado em <?= e((string) ($explainability['last_refreshed_at'] ?? '')) ?></span></div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Perfis sinalizados</div><div class="value"><?= (int) ($overview['users_flagged'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Denúncias pendentes</div><div class="value"><?= (int) ($overview['reports_pending'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Fila alta prioridade</div><div class="value"><?= (int) ($queue['high'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Fila média prioridade</div><div class="value"><?= (int) ($queue['medium'] ?? 0) ?></div></div></div></div>
</div>

<div class="rd-card mb-3"><div class="card-body">
  <form method="get" class="d-flex gap-2 align-items-center">
    <label class="small text-muted">Filtrar prioridade</label>
    <select name="priority" class="form-select form-select-sm" style="max-width:200px"><option value="">todas</option><option value="alta" <?= $currentPriority === 'alta' ? 'selected' : '' ?>>alta</option><option value="média" <?= $currentPriority === 'média' ? 'selected' : '' ?>>média</option><option value="baixa" <?= $currentPriority === 'baixa' ? 'selected' : '' ?>>baixa</option></select>
    <button class="btn btn-sm btn-outline-primary">Aplicar</button>
  </form>
</div></div>

<div class="rd-card mb-3"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
<thead><tr><th>Utilizador</th><th>Status</th><th>Sinais</th><th>Score</th><th>Razões</th><th>Acção</th></tr></thead>
<tbody>
<?php foreach ($users as $user): ?>
  <tr>
    <td>#<?= (int) $user['id'] ?> · <?= e((string) $user['first_name']) ?> <?= e((string) $user['last_name']) ?><br><small><?= e((string) $user['email']) ?></small></td>
    <td><?= e((string) $user['status']) ?></td>
    <td><small>Denúncias: <?= (int) $user['reports_count'] ?> (30d: <?= (int) $user['reports_30_days'] ?>)</small><br><small>Bloqueios: <?= (int) $user['blocked_count'] ?></small><br><small>Msgs 24h: <?= (int) $user['messages_24h'] ?> · Convites 24h: <?= (int) $user['invites_24h'] ?></small><br><small>Aceitação convites 30d: <?= e((string) ($user['acceptance_rate_30d'] ?? 0)) ?>%</small></td>
    <td><span class="rd-badge badge-active"> <?= (int) ($user['risk_score'] ?? 0) ?> · <?= e((string) ($user['risk_priority'] ?? 'baixa')) ?></span><br><small><?= e((string) ($user['priority_label'] ?? '')) ?></small></td>
    <td><ul class="small mb-0"><?php foreach (($user['risk_reasons'] ?? []) as $reason): ?><li><?= e((string) $reason) ?></li><?php endforeach; ?></ul></td>
    <td><a class="btn btn-sm btn-outline-dark mb-1" href="<?= e((string) ($user['user_detail_url'] ?? '#')) ?>">Detalhe</a><a class="btn btn-sm btn-outline-primary mb-1" href="<?= e((string) ($user['moderation_url'] ?? '/admin/moderation')) ?>">Moderação</a><a class="btn btn-sm btn-outline-secondary mb-1" href="<?= e((string) ($user['audit_url'] ?? ('/admin/audit?target_type=user&target_id=' . (int) ($user['id'] ?? 0)))) ?>">Auditoria</a></td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>

<div class="row g-3">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body table-responsive"><h6>Anomalias em convites (24h)</h6><table class="table table-sm"><thead><tr><th>Utilizador</th><th>Convites</th><th>Taxa aceitação</th></tr></thead><tbody><?php foreach ($inviteAnomalies as $row): ?><tr><td>#<?= (int) $row['sender_id'] ?> · <?= e((string) $row['sender_name']) ?></td><td><?= (int) $row['invites_24h'] ?></td><td><?= e((string) ($row['acceptance_rate_24h'] ?? 0)) ?>%</td></tr><?php endforeach; ?></tbody></table></div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body table-responsive"><h6>Anomalias em mensagens (24h)</h6><table class="table table-sm"><thead><tr><th>Utilizador</th><th>Mensagens</th><th>Msgs / conversa</th></tr></thead><tbody><?php foreach ($messageAnomalies as $row): ?><tr><td>#<?= (int) $row['sender_id'] ?> · <?= e((string) $row['sender_name']) ?></td><td><?= (int) $row['messages_24h'] ?></td><td><?= e((string) ($row['messages_per_conversation'] ?? 0)) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
