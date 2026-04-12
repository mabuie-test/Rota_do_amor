<?php $metrics = $metrics ?? []; $product = $metrics['product'] ?? []; $operations = $metrics['operations'] ?? []; $finance = $metrics['finance'] ?? []; $diary = $metrics['diary'] ?? []; $risk = $metrics['risk'] ?? []; ?>
<h3 class="mb-3">Dashboard Executivo · Super Admin</h3>

<div class="row g-3 mb-1">
  <div class="col-lg-4"><div class="rd-card"><div class="card-body">
    <h6 class="mb-2">Produto</h6>
    <p class="small mb-1">Total de utilizadores: <strong><?= (int) ($product['total_users'] ?? 0) ?></strong></p>
    <p class="small mb-1">Novos 7 dias: <strong><?= (int) ($product['new_users_7_days'] ?? 0) ?></strong> (período anterior: <?= (int) ($product['new_users_prev_7_days'] ?? 0) ?>)</p>
    <p class="small mb-1">Activações pagas: <strong><?= (int) ($product['paid_activations'] ?? 0) ?></strong></p>
    <p class="small mb-1">Subscrições activas: <strong><?= (int) ($product['active_subscriptions'] ?? 0) ?></strong></p>
    <p class="small mb-0">Boosts activos: <strong><?= (int) ($product['active_boosts'] ?? 0) ?></strong></p>
  </div></div></div>
  <div class="col-lg-4"><div class="rd-card"><div class="card-body">
    <h6 class="mb-2">Operação & Moderação</h6>
    <p class="small mb-1">Verificações pendentes: <strong><?= (int) ($operations['pending_verifications'] ?? 0) ?></strong></p>
    <p class="small mb-1">Denúncias pendentes: <strong><?= (int) ($operations['pending_reports'] ?? 0) ?></strong></p>
    <p class="small mb-1">Suspensos/Banidos: <strong><?= (int) ($operations['suspended_or_banned'] ?? 0) ?></strong></p>
    <p class="small mb-0">Eventos auditáveis 24h: <strong><?= (int) ($operations['audit_events_24h'] ?? 0) ?></strong></p>
  </div></div></div>
  <div class="col-lg-4"><div class="rd-card"><div class="card-body">
    <h6 class="mb-2">Finanças</h6>
    <p class="small mb-1">Pagamentos concluídos: <strong><?= (int) ($finance['payments_completed'] ?? 0) ?></strong></p>
    <p class="small mb-1">Pagamentos pendentes: <strong><?= (int) ($finance['payments_pending'] ?? 0) ?></strong></p>
    <p class="small mb-1">Pagamentos falhados (7d): <strong><?= (int) ($finance['payments_failed_7_days'] ?? 0) ?></strong></p>
    <p class="small mb-1">Receita 7d: <strong><?= e((string) ($finance['revenue_7_days'] ?? 0)) ?></strong></p>
    <p class="small mb-0">Receita 30d: <strong><?= e((string) ($finance['revenue_30_days'] ?? 0)) ?></strong></p>
  </div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Diário & retenção (agregado institucional)</h6>
    <p class="small mb-1">Entradas totais: <strong><?= (int) ($diary['total_entries'] ?? 0) ?></strong></p>
    <p class="small mb-1">Utilizadores activos 7/30 dias: <strong><?= (int) ($diary['active_users_7_days'] ?? 0) ?></strong> / <strong><?= (int) ($diary['active_users_30_days'] ?? 0) ?></strong></p>
    <p class="small mb-1">Entradas 7/30 dias: <strong><?= (int) ($diary['entries_last_7_days'] ?? 0) ?></strong> / <strong><?= (int) ($diary['entries_last_30_days'] ?? 0) ?></strong></p>
    <p class="small mb-1">Média entradas por utilizador: <strong><?= e((string) ($diary['avg_entries_per_user'] ?? 0)) ?></strong> (30d: <?= e((string) ($diary['entries_per_user_30_days'] ?? 0)) ?>)</p>
    <p class="small mb-1">Retenção diário vs não diário: <strong><?= e((string) ($diary['retention_diary_users_30_days'] ?? 0)) ?>%</strong> vs <strong><?= e((string) ($diary['retention_non_diary_users_30_days'] ?? 0)) ?>%</strong></p>
    <p class="small mb-1">Lift de retenção: <strong><?= e((string) ($diary['retention_lift_points'] ?? 0)) ?> pontos</strong></p>
    <p class="small mb-2">Sinal de consistência emocional: <strong><?= e((string) ($diary['consistency_signal'] ?? 0)) ?></strong> dias activos médios no mês</p>
    <?php if (!empty($diary['mood_distribution_30_days'])): ?><div class="d-flex gap-2 flex-wrap"><?php foreach (($diary['mood_distribution_30_days'] ?? []) as $mood): ?><span class="rd-badge badge-active"><?= e((string) $mood['mood_label']) ?>: <?= (int) $mood['total'] ?></span><?php endforeach; ?></div><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Risco operacional</h6>
    <p class="small mb-1">Utilizadores sinalizados: <strong><?= (int) ($risk['users_flagged'] ?? 0) ?></strong></p>
    <p class="small mb-1">Denúncias pendentes: <strong><?= (int) ($risk['reports_pending'] ?? 0) ?></strong></p>
    <p class="small mb-1">Alvos reincidentes 30d: <strong><?= (int) ($risk['reports_recurrent_targets_30d'] ?? 0) ?></strong></p>
    <p class="small mb-2">Picos de mensagens 24h: <strong><?= (int) ($risk['high_message_spike_users_24h'] ?? 0) ?></strong></p>
    <a class="btn btn-sm btn-rd-soft" href="/admin/risk">Abrir centro de risco</a>
  </div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Alertas críticos</h6>
    <?php if (empty($metrics['critical_alerts'])): ?><p class="small text-muted mb-0">Sem alertas críticos no momento.</p>
    <?php else: ?><ul class="small mb-0"><?php foreach (($metrics['critical_alerts'] ?? []) as $alert): ?><li><strong><?= e((string) (($alert['severity'] ?? 'info'))) ?>:</strong> <?= e((string) ($alert['message'] ?? '')) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Exige acção</h6>
    <?php if (empty($metrics['action_required'])): ?><p class="small text-muted mb-0">Sem filas críticas abertas.</p>
    <?php else: ?><?php foreach (($metrics['action_required'] ?? []) as $task): ?><a class="btn btn-sm btn-outline-primary me-2 mb-2" href="<?= e((string) $task['url']) ?>"><?= e((string) $task['label']) ?> (<?= (int) $task['count'] ?>)</a><?php endforeach; ?><?php endif; ?>
  </div></div></div>
</div>
