<?php $d = $dashboard ?? []; $signals = $d['profile_signals'] ?? []; $verification = $d['verification_progress'] ?? []; $retention = $d['retention_context'] ?? []; $boostImpact = $d['boost_impact'] ?? []; $premium = $d['premium_context'] ?? []; ?>
<h3 class="mb-3">Dashboard</h3>
<?php if (!empty($d['primary_focus'])): ?>
<div class="alert alert-primary py-2 px-3 mb-3">
  <strong>Foco de hoje:</strong> <?= e((string) $d['primary_focus']) ?>
</div>
<?php endif; ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Estado da Conta</div><div class="value"><?= e((string) ($d['account_status'] ?? 'pending')) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Subscrição</div><div class="value"><?= !empty($d['subscription_active']) ? 'Activa' : 'Inactiva' ?></div><small><?= (int) ($d['days_remaining'] ?? 0) ?> dias restantes</small></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Mensagens não lidas</div><div class="value"><?= (int) ($d['unread_messages'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Total de Matches</div><div class="value"><?= (int) ($d['total_matches'] ?? 0) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="rd-card"><div class="card-body">
      <h6>Perfil: completude e atratividade</h6>
      <div class="progress mb-2"><div class="progress-bar" role="progressbar" style="width: <?= (int) ($d['profile_completion_percent'] ?? 0) ?>%"></div></div>
      <p class="small text-muted mb-1">Completude: <strong><?= (int) ($d['profile_completion_percent'] ?? 0) ?>%</strong> · Atratividade: <strong><?= (int) ($d['profile_attractiveness_percent'] ?? 0) ?>%</strong></p>
      <p class="small text-muted mb-2">Confiança: <strong><?= e((string) ($d['trust_indicator'] ?? 'Baixa')) ?></strong></p>
      <?php if (!empty($d['profile_checklist'])): ?>
        <ul class="small mb-0"><?php foreach ($d['profile_checklist'] as $item => $ok): ?><li><?= $ok ? '✅' : '⬜' ?> <?= e((string) $item) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="rd-card"><div class="card-body">
      <h6>Boost, verificação e retenção</h6>
      <p class="small mb-1">Boost activo: <strong class="<?= !empty($d['boost_active']) ? 'text-success' : 'text-warning' ?>"><?= !empty($d['boost_active']) ? 'Sim' : 'Não' ?></strong> <?php if (!empty($boostImpact['next_ends_at'])): ?>· termina em <?= e((string) $boostImpact['next_ends_at']) ?><?php endif; ?></p>
      <p class="small mb-1">Impacto estimado do boost: <strong><?= e((string) ($premium['boost_estimated_impact'] ?? 'visibilidade normal')) ?></strong></p>
      <p class="small mb-1">Readiness para boost: <strong><?= (int) ($premium['boost_readiness_score'] ?? 0) ?>%</strong> · boosts activos: <strong><?= (int) ($premium['boost_active_count'] ?? 0) ?></strong></p>
      <p class="small mb-1">Compatibilidade média: <strong><?= e((string) ($d['avg_compatibility'] ?? '0')) ?>%</strong></p>
      <p class="small mb-1">Fotos: <strong><?= (int) ($signals['photos_count'] ?? 0) ?></strong> · Interesses: <strong><?= (int) ($signals['interests_count'] ?? 0) ?></strong></p>
      <p class="small mb-1">Verificação: <strong class="<?= (($verification['status'] ?? 'not_started') === 'approved') ? 'text-success' : 'text-warning' ?>"><?= e((string) ($verification['label'] ?? 'Não iniciada')) ?></strong> <?php if (!empty($verification['updated_at'])): ?>· actualizada em <?= e((string) $verification['updated_at']) ?><?php endif; ?></p>
      <p class="small mb-1">Contexto premium: <strong><?= e((string) ($premium['subscription_state'] ?? 'expirada')) ?></strong> · urgência <strong><?= e((string) ($premium['subscription_urgency'] ?? 'alta')) ?></strong></p>
      <p class="small mb-1">Risco de retenção: <strong><?= e((string) ($retention['risk_level'] ?? 'baixo')) ?></strong> · Engajamento: <strong><?= e((string) ($retention['engagement_signal'] ?? 'frio')) ?></strong></p>
      <p class="small mb-3">Última actividade: <?= e((string) ($d['last_activity_at'] ?? '---')) ?></p>
      <?php if (!empty($d['actions'])): ?>
        <p class="small fw-semibold mb-1">Ações prioritárias</p>
        <?php foreach (($d['actions'] ?? []) as $index => $action): ?>
          <a class="btn btn-sm btn-rd-primary me-2 mb-2" href="<?= e((string) $action['url']) ?>"><?= ($index + 1) ?>. <?= e((string) $action['label']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
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
