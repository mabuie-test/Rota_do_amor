<?php $metrics = $metrics ?? []; ?>
<h3 class="mb-3">Dashboard Executivo · Super Admin</h3>
<div class="row g-3">
  <?php foreach (['total_users','new_users_7_days','paid_activations','active_subscriptions','active_boosts','pending_verifications','pending_reports','suspended_or_banned','payments_completed','payments_pending','revenue_30_days'] as $key): ?>
    <div class="col-md-4 col-xl-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted"><?= e($key) ?></div><div class="value"><?= e((string) ($metrics[$key] ?? 0)) ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Analytics agregados do Diário</h6>
    <?php $d = $metrics['diary'] ?? []; ?>
    <p class="small mb-1">Total de entradas: <strong><?= (int) ($d['total_entries'] ?? 0) ?></strong></p>
    <p class="small mb-1">Utilizadores que usam diário: <strong><?= (int) ($d['users_with_entries'] ?? 0) ?></strong></p>
    <p class="small mb-1">Entradas últimos 7 dias: <strong><?= (int) ($d['entries_last_7_days'] ?? 0) ?></strong></p>
    <p class="small mb-0">Média por utilizador: <strong><?= e((string) ($d['avg_entries_per_user'] ?? 0)) ?></strong></p>
  </div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Alertas críticos</h6>
    <?php if (empty($metrics['critical_alerts'])): ?><p class="small text-muted mb-0">Sem alertas críticos no momento.</p>
    <?php else: ?><ul class="small mb-0"><?php foreach (($metrics['critical_alerts'] ?? []) as $alert): ?><li><?= e((string) $alert) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
</div>
