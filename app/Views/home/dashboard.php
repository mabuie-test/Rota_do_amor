<?php $d = $dashboard ?? []; ?>
<h3 class="mb-3">Dashboard</h3>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Estado da Conta</div><div class="value"><?= e((string) ($d['account_status'] ?? 'pending')) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Subscrição</div><div class="value"><?= !empty($d['subscription_active']) ? 'Activa' : 'Inactiva' ?></div><small><?= (int) ($d['days_remaining'] ?? 0) ?> dias restantes</small></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Mensagens não lidas</div><div class="value"><?= (int) ($d['unread_messages'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Total de Matches</div><div class="value"><?= (int) ($d['total_matches'] ?? 0) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="rd-card"><div class="card-body">
      <h6>Qualidade do Perfil</h6>
      <div class="progress mb-2"><div class="progress-bar" role="progressbar" style="width: <?= (int) ($d['profile_completion_percent'] ?? 0) ?>%"></div></div>
      <p class="small text-muted mb-2">Completude: <?= (int) ($d['profile_completion_percent'] ?? 0) ?>%</p>
      <?php if (!empty($d['profile_missing_items'])): ?>
        <ul class="small mb-0"><?php foreach ($d['profile_missing_items'] as $item): ?><li><?= e((string) $item) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
        <p class="small mb-0">Perfil completo. Excelente!</p>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="rd-card"><div class="card-body">
      <h6>Retenção & Premium</h6>
      <p class="small mb-1">Boost activo: <strong><?= !empty($d['boost_active']) ? 'Sim' : 'Não' ?></strong></p>
      <p class="small mb-1">Compatibilidade média: <strong><?= e((string) ($d['avg_compatibility'] ?? '0')) ?>%</strong></p>
      <p class="small mb-3">Última actividade: <?= e((string) ($d['last_activity_at'] ?? '---')) ?></p>
      <?php foreach (($d['actions'] ?? []) as $action): ?>
        <a class="btn btn-sm btn-rd-primary me-2 mb-2" href="<?= e((string) $action['url']) ?>"><?= e((string) $action['label']) ?></a>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>

<?php if (!empty($d['alerts'])): ?>
<div class="rd-card mt-3"><div class="card-body">
  <h6>Alertas importantes</h6>
  <ul class="mb-0"><?php foreach ($d['alerts'] as $alert): ?><li><?= e((string) $alert) ?></li><?php endforeach; ?></ul>
</div></div>
<?php endif; ?>

<?php if (!empty($d['active_badges'])): ?>
<div class="rd-card mt-3"><div class="card-body">
  <h6>Badges activos</h6>
  <?php foreach ($d['active_badges'] as $badge): ?>
    <span class="badge bg-primary me-1"><?= e((string) ($badge['badge_type'] ?? 'badge')) ?></span>
  <?php endforeach; ?>
</div></div>
<?php endif; ?>
</div>
