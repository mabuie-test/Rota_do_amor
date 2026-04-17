<?php $metrics = $metrics ?? []; $product = $metrics['product'] ?? []; $operations = $metrics['operations'] ?? []; $finance = $metrics['finance'] ?? []; $diary = $metrics['diary'] ?? []; $risk = $metrics['risk'] ?? []; $trend = $metrics['trend'] ?? []; $blocks = $metrics['executive_blocks'] ?? []; $safeDateTrend = $trend['safe_dates_daily_trend_30_days'] ?? []; ?>

<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-chart-line"></i></span>Dashboard Executivo · Super Admin</h3>
    <p class="rd-page-header__subtitle">Leitura institucional de produto, operação, risco, finanças e saúde da plataforma em painéis com ação contextual.</p>
  </div>
  <a class="btn btn-sm btn-rd-soft" href="/admin/risk"><i class="fa-solid fa-shield-halved me-1"></i>Centro de risco</a>
</div>

<?php foreach (($metrics['warnings'] ?? []) as $warning): ?><div class="alert alert-warning rd-alert py-2"><i class="fa-solid fa-triangle-exclamation"></i><span><?= e((string) $warning) ?></span></div><?php endforeach; ?>

<div class="rd-metric-grid mb-3">
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-user-plus"></i>Novos utilizadores 7d</div><div class="rd-metric-card__value"><?= (int) ($product['new_users_7_days'] ?? 0) ?></div><div class="rd-metric-card__meta">variação <?= e((string) ($trend['new_users_variation_7_days'] ?? 0)) ?>%</div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-money-bill-trend-up"></i>Receita 30d</div><div class="rd-metric-card__value"><?= e((string) ($finance['revenue_30_days'] ?? 0)) ?></div><div class="rd-metric-card__meta">variação <?= e((string) ($trend['revenue_variation_30_days'] ?? 0)) ?>%</div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-calendar-check"></i>Encontros seguros 30d</div><div class="rd-metric-card__value"><?= (int) ($product['safe_dates_proposed_30_days'] ?? 0) ?></div><div class="rd-metric-card__meta">aceite <?= e((string) ($product['safe_dates_acceptance_rate_30_days'] ?? 0)) ?>%</div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-fire-flame-curved"></i>Boosts ativos</div><div class="rd-metric-card__value"><?= (int) ($product['active_boosts'] ?? 0) ?></div><div class="rd-metric-card__meta">subscrições ativas <?= (int) ($product['active_subscriptions'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-user-shield"></i>Utilizadores sinalizados</div><div class="rd-metric-card__value"><?= (int) ($risk['users_flagged'] ?? 0) ?></div><div class="rd-metric-card__meta">denúncias pendentes <?= (int) ($risk['reports_pending'] ?? 0) ?></div></div>
  <div class="rd-metric-card"><div class="rd-metric-card__label"><i class="fa-solid fa-book-heart"></i>Adoção diário 30d</div><div class="rd-metric-card__value"><?= e((string) ($diary['adoption_rate_30_days_percent'] ?? 0)) ?>%</div><div class="rd-metric-card__meta">entradas totais <?= (int) ($diary['total_entries'] ?? 0) ?></div></div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ($blocks as $key => $block): ?>
    <div class="col-lg-4">
      <div class="rd-card h-100"><div class="card-body">
        <div class="rd-card-header">
          <div>
            <h6 class="rd-card-header__title"><i class="fa-solid fa-layer-group"></i><?= e((string) ($block['title'] ?? ucfirst((string) $key))) ?></h6>
            <p class="rd-card-header__subtitle">Bloco executivo resumido.</p>
          </div>
        </div>
        <div class="rd-data-list">
          <?php foreach (($block['items'] ?? []) as $item): ?>
            <div class="rd-data-list__item"><span><?= e((string) ($item['label'] ?? '—')) ?></span><strong><?= e((string) ($item['value'] ?? 0)) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-4"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-cubes"></i>Produto</h6><div class="rd-data-list"><div class="rd-data-list__item"><span>Activações pagas</span><strong><?= (int) ($product['paid_activations'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Rota Diária concluídas (30d)</span><strong><?= (int) ($product['daily_routes_completed_30_days'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Claims recompensa (30d)</span><strong><?= (int) ($product['daily_routes_rewards_claimed_30_days'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Match → conversa (30d)</span><strong><?= e((string) ($product['match_to_conversation_30_days'] ?? 0)) ?>%</strong></div></div></div></div></div>
  <div class="col-lg-4"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-briefcase"></i>Operação & moderação</h6><div class="rd-data-list"><div class="rd-data-list__item"><span>Verificações pendentes</span><strong><?= (int) ($operations['pending_verifications'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Denúncias pendentes</span><strong><?= (int) ($operations['pending_reports'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Suspensos/Banidos</span><strong><?= (int) ($operations['suspended_or_banned'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Eventos auditáveis 24h</span><strong><?= (int) ($operations['audit_events_24h'] ?? 0) ?></strong></div></div></div></div></div>
  <div class="col-lg-4"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-wallet"></i>Finanças</h6><div class="rd-data-list"><div class="rd-data-list__item"><span>Pagamentos concluídos</span><strong><?= (int) ($finance['payments_completed'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Pagamentos pendentes</span><strong><?= (int) ($finance['payments_pending'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Pagamentos falhados (7d)</span><strong><?= (int) ($finance['payments_failed_7_days'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Receita 7d</span><strong><?= e((string) ($finance['revenue_7_days'] ?? 0)) ?></strong></div></div></div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-book-open-reader"></i>Diário & retenção</h6><p class="rd-supporting-text mb-2">Atividade emocional agregada e sinais de retenção.</p><div class="rd-data-list"><div class="rd-data-list__item"><span>Com entradas / sem entradas</span><strong><?= (int) ($diary['users_with_entries'] ?? 0) ?> / <?= (int) ($diary['users_without_entries'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Utilizadores ativos 7/30 dias</span><strong><?= (int) ($diary['active_users_7_days'] ?? 0) ?> / <?= (int) ($diary['active_users_30_days'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Retenção diário vs não diário</span><strong><?= e((string) ($diary['retention_diary_users_30_days'] ?? 0)) ?>% vs <?= e((string) ($diary['retention_non_diary_users_30_days'] ?? 0)) ?>%</strong></div></div></div></div></div>
  <div class="col-lg-6"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-shield-heart"></i>Encontro Seguro</h6><p class="rd-supporting-text mb-2">Saúde do módulo e volume de sinais de segurança.</p><div class="rd-data-list"><div class="rd-data-list__item"><span>Utilizadores no módulo (30d)</span><strong><?= (int) ($product['safe_dates_users_using_module_30_days'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Sinais de segurança (30d)</span><strong><?= (int) ($product['safe_dates_safety_signals_30_days'] ?? 0) ?></strong></div><div class="rd-data-list__item"><span>Tendência diária disponível</span><strong><?= count($safeDateTrend) ?> dias</strong></div></div><div class="mt-2"><a class="btn btn-sm btn-rd-soft me-2" href="/admin/safe-dates"><i class="fa-solid fa-table-cells-large me-1"></i>Abrir módulo</a><a class="btn btn-sm btn-outline-secondary" href="/admin/risk"><i class="fa-solid fa-link me-1"></i>Cruzar com risco</a></div></div></div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-siren-on"></i>Alertas críticos</h6><?php if (empty($metrics['critical_alerts'])): ?><div class="rd-empty"><div class="card-body py-4"><span class="rd-empty-icon"><i class="fa-solid fa-circle-check"></i></span><p class="rd-supporting-text mb-0">Sem alertas críticos no momento.</p></div></div><?php else: ?><ul class="small mb-0"><?php foreach (($metrics['critical_alerts'] ?? []) as $alert): ?><li><strong><?= e((string) (($alert['severity'] ?? 'info'))) ?>:</strong> <?= e((string) ($alert['message'] ?? '')) ?></li><?php endforeach; ?></ul><?php endif; ?></div></div></div>
  <div class="col-lg-6"><div class="rd-card h-100"><div class="card-body"><h6 class="rd-card-header__title"><i class="fa-solid fa-bolt"></i>Exige ação</h6><?php if (empty($metrics['action_required'])): ?><div class="rd-empty"><div class="card-body py-4"><span class="rd-empty-icon"><i class="fa-solid fa-list-check"></i></span><p class="rd-supporting-text mb-0">Sem filas críticas abertas.</p></div></div><?php else: ?><?php foreach (($metrics['action_required'] ?? []) as $task): ?><a class="btn btn-sm btn-outline-primary me-2 mb-2" href="<?= e((string) $task['url']) ?>"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e((string) $task['label']) ?> (<?= (int) $task['count'] ?>)</a><?php endforeach; ?><?php endif; ?></div></div></div>
</div>
