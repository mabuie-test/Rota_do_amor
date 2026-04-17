<?php $users = $users ?? []; $overview = $overview ?? []; $inviteAnomalies = $invites_anomalies ?? []; $messageAnomalies = $messages_anomalies ?? []; $safeDateAnomalies = $safe_dates_anomalies ?? []; $queue = $priority_queue ?? []; $currentPriority = $current_priority ?? ''; $explainability = $explainability ?? []; ?>
<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-shield-halved"></i></span>Centro de Risco & Abuso</h3>
    <p class="rd-page-header__subtitle">Monitorização operacional com leitura por prioridade, sinais contextuais e trilha de ação administrativa.</p>
  </div>
</div>

<?php foreach (($warnings ?? []) as $warning): ?><div class="alert alert-warning rd-alert py-2"><i class="fa-solid fa-triangle-exclamation"></i><span><?= e((string) $warning) ?></span></div><?php endforeach; ?>
<?php if (!empty($explainability)): ?><div class="alert alert-light border rd-alert py-2"><i class="fa-solid fa-brain"></i><span><strong>Modelo:</strong> <?= e((string) ($explainability['method'] ?? 'Score composto')) ?> · <span class="rd-meta-text">atualizado em <?= e((string) ($explainability['last_refreshed_at'] ?? '')) ?></span></span></div><?php endif; ?>

<div class="rd-metric-grid mb-3">
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-user-shield"></i>Perfis sinalizados</div><div class="rd-metric-card__value"><?= (int) ($overview['users_flagged'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-flag"></i>Denúncias pendentes</div><div class="rd-metric-card__value"><?= (int) ($overview['reports_pending'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-fire"></i>Fila alta prioridade</div><div class="rd-metric-card__value"><?= (int) ($queue['high'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-calendar-xmark"></i>Spike recusa (30d)</div><div class="rd-metric-card__value"><?= (int) ($overview['safe_dates_decline_spike_30d'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-clock-rotate-left"></i>Spike remarcação (30d)</div><div class="rd-metric-card__value"><?= (int) ($overview['safe_dates_reschedule_spike_30d'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-shield-heart"></i>Sinais segurança (30d)</div><div class="rd-metric-card__value"><?= (int) ($overview['safe_dates_safety_signals_30d'] ?? 0) ?></div></div>
</div>

<div class="rd-card mb-3"><div class="card-body">
  <form method="get" class="rd-form-section d-flex gap-2 align-items-end flex-wrap">
    <div>
      <label class="form-label">Filtrar prioridade</label>
      <select name="priority" class="form-select form-select-sm" style="min-width:220px"><option value="">todas</option><option value="alta" <?= $currentPriority === 'alta' ? 'selected' : '' ?>>alta</option><option value="média" <?= $currentPriority === 'média' ? 'selected' : '' ?>>média</option><option value="baixa" <?= $currentPriority === 'baixa' ? 'selected' : '' ?>>baixa</option></select>
    </div>
    <button class="btn btn-sm btn-rd-primary"><i class="fa-solid fa-filter me-1"></i>Aplicar filtro</button>
  </form>
</div></div>

<div class="rd-card mb-3"><div class="card-body">
  <div class="rd-card-header"><div><h6 class="rd-card-header__title"><i class="fa-solid fa-list-check"></i>Perfis monitorados</h6><p class="rd-card-header__subtitle">Score de risco + razões explicáveis + encaminhamento rápido.</p></div></div>
  <div class="table-responsive rd-table-shell">
<table class="table table-modern align-middle">
<thead><tr><th><i class="fa-solid fa-user me-1"></i>Utilizador</th><th>Status</th><th>Sinais</th><th>Score</th><th>Razões</th><th>Ação</th></tr></thead>
<tbody>
<?php foreach ($users as $user): ?>
  <tr>
    <td><div class="fw-semibold">#<?= (int) $user['id'] ?> · <?= e((string) $user['first_name']) ?> <?= e((string) $user['last_name']) ?></div><div class="rd-meta-text"><?= e((string) $user['email']) ?></div></td>
    <td><span class="rd-badge badge-pending"><?= e((string) $user['status']) ?></span></td>
    <td class="rd-meta-text">Denúncias: <?= (int) $user['reports_count'] ?> (30d: <?= (int) $user['reports_30_days'] ?>)<br>Bloqueios: <?= (int) $user['blocked_count'] ?><br>Msgs 24h: <?= (int) $user['messages_24h'] ?> · Convites: <?= (int) $user['invites_24h'] ?></td>
    <td><span class="rd-badge badge-active"><?= (int) ($user['risk_score'] ?? 0) ?> · <?= e((string) ($user['risk_priority'] ?? 'baixa')) ?></span><div class="rd-meta-text mt-1"><?= e((string) ($user['priority_label'] ?? '')) ?></div></td>
    <td><ul class="small mb-0"><?php foreach (($user['risk_reasons'] ?? []) as $reason): ?><li><?= e((string) $reason) ?></li><?php endforeach; ?></ul></td>
    <td><a class="btn btn-sm btn-outline-dark mb-1" href="<?= e((string) ($user['user_detail_url'] ?? '#')) ?>"><i class="fa-solid fa-binoculars me-1"></i>Detalhe</a><a class="btn btn-sm btn-outline-primary mb-1" href="<?= e((string) ($user['moderation_url'] ?? '/admin/moderation')) ?>"><i class="fa-solid fa-gavel me-1"></i>Moderação</a><a class="btn btn-sm btn-outline-secondary mb-1" href="<?= e((string) ($user['audit_url'] ?? ('/admin/audit?target_type=user&target_id=' . (int) ($user['id'] ?? 0)))) ?>"><i class="fa-solid fa-clock-rotate-left me-1"></i>Auditoria</a></td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div></div>

<div class="row g-3">
  <div class="col-lg-6"><div class="rd-card h-100"><div class="card-body table-responsive"><h6 class="rd-card-header__title"><i class="fa-solid fa-envelope-circle-check"></i>Anomalias em convites (24h)</h6><table class="table table-sm"><thead><tr><th>Utilizador</th><th>Convites</th><th>Taxa aceitação</th></tr></thead><tbody><?php foreach ($inviteAnomalies as $row): ?><tr><td>#<?= (int) $row['sender_id'] ?> · <?= e((string) $row['sender_name']) ?></td><td><?= (int) $row['invites_24h'] ?></td><td><?= e((string) ($row['acceptance_rate_24h'] ?? 0)) ?>%</td></tr><?php endforeach; ?></tbody></table></div></div></div>
  <div class="col-lg-6"><div class="rd-card h-100"><div class="card-body table-responsive"><h6 class="rd-card-header__title"><i class="fa-solid fa-comments"></i>Anomalias em mensagens (24h)</h6><table class="table table-sm"><thead><tr><th>Utilizador</th><th>Mensagens</th><th>Msgs / conversa</th></tr></thead><tbody><?php foreach ($messageAnomalies as $row): ?><tr><td>#<?= (int) $row['sender_id'] ?> · <?= e((string) $row['sender_name']) ?></td><td><?= (int) $row['messages_24h'] ?></td><td><?= e((string) ($row['messages_per_conversation'] ?? 0)) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
  <div class="col-lg-12"><div class="rd-card"><div class="card-body table-responsive"><h6 class="rd-card-header__title"><i class="fa-solid fa-shield-virus"></i>Anomalias em Encontro Seguro (30d)</h6><table class="table table-sm"><thead><tr><th>Utilizador</th><th>Encontros</th><th>Recusas</th><th>Cancelamentos</th><th>Taxa recusa</th></tr></thead><tbody><?php foreach ($safeDateAnomalies as $row): ?><tr><td>#<?= (int) $row['sender_id'] ?> · <?= e((string) $row['sender_name']) ?></td><td><?= (int) $row['safe_dates_30d'] ?></td><td><?= (int) $row['declined_30d'] ?></td><td><?= (int) $row['cancelled_30d'] ?></td><td><?= e((string) ($row['decline_rate_30d'] ?? 0)) ?>%</td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
