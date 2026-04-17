<?php $s = $summary ?? []; $policy = $premium_policy ?? []; ?>
<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-eye"></i></span>Radar de Visitantes</h3>
    <p class="rd-page-header__subtitle">Leitura de interesse recente com histórico temporal e recorrência por perfil.</p>
  </div>
</div>
<div class="rd-card mb-3"><div class="card-body">
  <div class="rd-card-header">
    <div>
      <h6 class="rd-card-header__title"><i class="fa-solid fa-chart-pie"></i>Resumo do radar</h6>
      <p class="rd-card-header__subtitle">Indicadores das últimas 24h e 7 dias.</p>
    </div>
  </div>
  <div class="rd-metric-grid">
    <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-clock"></i>Visitas 24h</div><div class="rd-metric-card__value"><?= (int) ($s['total_last_24h'] ?? 0) ?></div></div>
    <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-users"></i>Únicos 7d</div><div class="rd-metric-card__value"><?= (int) ($s['unique_last_7d'] ?? 0) ?></div></div>
    <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-repeat"></i>Repetições 7d</div><div class="rd-metric-card__value"><?= (int) ($s['repeat_visitors_last_7d'] ?? 0) ?></div></div>
  </div>
  <p class="rd-supporting-text mt-2 mb-0">Free vê <?= (int) ($policy['free_visible_visitors'] ?? 2) ?> visitantes com detalhe. Premium desbloqueia histórico de <?= (int) ($policy['premium_history_days'] ?? 30) ?> dias.</p>
  <?php if (!empty($s['premium_locked'])): ?><div class="alert alert-info rd-alert py-2 small mt-2 mb-0"><i class="fa-solid fa-lock"></i><span>Conta free com pistas limitadas. <a href="/premium">Tornar premium</a> para leitura completa.</span></div><?php endif; ?>
</div></div>

<div class="row g-3">
<?php foreach (($s['recent'] ?? []) as $row): ?>
  <div class="col-md-6">
    <div class="rd-card"><div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <p class="mb-1 fw-semibold"><i class="fa-solid fa-user me-1"></i><?= e((string) ($row['visitor_name'] ?? 'Visitante')) ?> <?php if ((int) ($row['visits_from_same'] ?? 1) >= 2): ?><span class="rd-badge badge-pending"><i class="fa-solid fa-repeat"></i>recorrente</span><?php endif; ?></p>
        <p class="rd-meta-text mb-0">Origem: <?= e((string) ($row['source_context'] ?? 'discover')) ?> · <?= e((string) ($row['created_at'] ?? '')) ?></p>
      </div>
      <?php if ((int) ($row['visitor_user_id'] ?? 0) > 0): ?><a class="btn btn-sm btn-rd-primary" href="/member/<?= (int) $row['visitor_user_id'] ?>"><i class="fa-solid fa-user-large me-1"></i>Ver perfil</a><?php else: ?><button class="btn btn-sm btn-outline-secondary" disabled>Bloqueado</button><?php endif; ?>
    </div></div>
  </div>
<?php endforeach; ?>
</div>
