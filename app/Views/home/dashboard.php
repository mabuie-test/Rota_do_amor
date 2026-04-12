<?php $d = $dashboard ?? []; $signals = $d['profile_signals'] ?? []; $verification = $d['verification_progress'] ?? []; $retention = $d['retention_context'] ?? []; $boostImpact = $d['boost_impact'] ?? []; $premium = $d['premium_context'] ?? []; $heartMode = $d['heart_mode'] ?? []; ?>
<h3 class="mb-3">Dashboard</h3>
<?php $nextSafeDate = $d['next_safe_date'] ?? []; ?>
<?php if (!empty($nextSafeDate)): ?>
<div class="alert alert-info py-2 px-3 mb-3">
  <strong><i class="fa-solid fa-shield-heart me-1"></i>Próximo Encontro Seguro:</strong>
  <?= e((string) ($nextSafeDate['title'] ?? 'Encontro Seguro')) ?> em <?= e((string) ($nextSafeDate['proposed_datetime'] ?? '')) ?> · <?= e((string) ($nextSafeDate['proposed_location'] ?? '')) ?>
  <a class="btn btn-sm btn-outline-primary ms-2" href="/dates/<?= (int) ($nextSafeDate['id'] ?? 0) ?>">Abrir</a>
</div>
<?php endif; ?>
<?php if (!empty($d['primary_focus'])): ?>
<div class="alert alert-primary py-2 px-3 mb-3 rd-alert">
  <strong><i class="fa-solid fa-stars me-1"></i>Foco de hoje:</strong> <?= e((string) $d['primary_focus']) ?>
</div>
<?php endif; ?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Estado da Conta</div><div class="value"><?= e((string) ($d['account_status'] ?? 'pending')) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Subscrição</div><div class="value"><?= !empty($d['subscription_active']) ? 'Activa' : 'Inactiva' ?></div><small><?= (int) ($d['days_remaining'] ?? 0) ?> dias restantes</small></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Mensagens não lidas</div><div class="value"><?= (int) ($d['unread_messages'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Total de Matches</div><div class="value"><?= (int) ($d['total_matches'] ?? 0) ?></div></div></div></div>
</div>

<div class="row g-3 mb-3 mt-1">
  <div class="col-lg-12">
    <div class="rd-card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0">Convites com Intenção + Quem Gostou de Mim</h6>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-rd-primary" href="/invites/received"><i class="fa-solid fa-heart me-1"></i>Ver recebidos</a>
          <a class="btn btn-sm btn-rd-soft" href="/invites/sent"><i class="fa-solid fa-paper-plane me-1"></i>Ver enviados</a>
        </div>
      </div>
      <p class="small mb-2">Pendentes recebidos: <strong><?= (int) ($d['pending_received_invites'] ?? 0) ?></strong> · Prioritários: <strong><?= (int) ($d['pending_priority_invites'] ?? 0) ?></strong> · Convites aceites enviados: <strong><?= (int) ($d['accepted_invites_total'] ?? 0) ?></strong></p>
      <?php if (!empty($d['likes_me_preview'])): ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach (($d['likes_me_preview'] ?? []) as $preview): ?>
            <span class="rd-badge badge-active"><i class="fa-solid fa-user-heart"></i><?= e((string) ($preview['sender_name'] ?? 'Perfil')) ?> · <?= (float) ($preview['compatibility_score_snapshot'] ?? 0) ?>%</span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="small text-muted">Ainda sem convites pendentes no momento.</div>
      <?php endif; ?>
    </div></div>
  </div>
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
      <h6>Modo do Coração & Ritmo Relacional</h6>
      <div class="rd-heart-mode-card mb-2">
        <div class="d-flex gap-2 flex-wrap mb-2">
          <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($heartMode['intention_icon'] ?? 'fa-heart-pulse')) ?>"></i><?= e((string) ($heartMode['intention_label'] ?? 'Conhecer sem pressão')) ?></span>
          <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($heartMode['pace_icon'] ?? 'fa-wave-square')) ?>"></i><?= e((string) ($heartMode['pace_label'] ?? 'Equilibrado')) ?></span>
        </div>
        <div class="small text-muted">Alinhamento médio de intenção: <strong><?= (float) ($d['avg_intention_alignment'] ?? 0) ?>%</strong> · Compatibilidade média de ritmo: <strong><?= (float) ($d['avg_pace_alignment'] ?? 0) ?>%</strong></div>
      </div>
      <?php if (!empty($d['heart_mode_should_refresh'])): ?>
        <div class="small text-warning mb-2"><i class="fa-solid fa-hourglass-half me-1"></i>Dica: atualize seu modo para refletir seu momento atual.</div>
      <?php endif; ?>
      <a class="btn btn-sm btn-rd-soft" href="/profile"><i class="fa-solid fa-hand-holding-heart me-1"></i>Atualizar modo</a>
    </div></div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-12">
    <div class="rd-card"><div class="card-body">
      <h6>Boost, verificação e retenção</h6>
      <p class="small mb-1">Boost activo: <strong class="<?= !empty($d['boost_active']) ? 'text-success' : 'text-warning' ?>"><?= !empty($d['boost_active']) ? 'Sim' : 'Não' ?></strong> <?php if (!empty($boostImpact['next_ends_at'])): ?>· termina em <?= e((string) $boostImpact['next_ends_at']) ?><?php endif; ?></p>
      <p class="small mb-1">Impacto estimado do boost: <strong><?= e((string) ($premium['boost_estimated_impact'] ?? 'visibilidade normal')) ?></strong></p>
      <p class="small mb-1">Readiness para boost: <strong><?= (int) ($premium['boost_readiness_score'] ?? 0) ?>%</strong> · boosts activos: <strong><?= (int) ($premium['boost_active_count'] ?? 0) ?></strong></p>
      <p class="small mb-1">Compatibilidade média: <strong><?= e((string) ($d['avg_compatibility'] ?? '0')) ?>%</strong></p>
      <p class="small mb-1">Fotos: <strong><?= (int) ($signals['photos_count'] ?? 0) ?></strong> · Interesses: <strong><?= (int) ($signals['interests_count'] ?? 0) ?></strong></p>
      <p class="small mb-1">Verificação: <strong class="<?= (($verification['status'] ?? 'not_started') === 'approved') ? 'text-success' : 'text-warning' ?>"><?= e((string) ($verification['label'] ?? 'Não iniciada')) ?></strong> <?php if (!empty($verification['updated_at'])): ?>· actualizada em <?= e((string) $verification['updated_at']) ?><?php endif; ?></p>
      <p class="small mb-1">Contexto premium: <strong><?= e((string) ($premium['subscription_state'] ?? 'expirada')) ?></strong> · urgência <strong><?= e((string) ($premium['subscription_urgency'] ?? 'alta')) ?></strong></p>
      <p class="small mb-1">Plano Encontro Seguro: <strong><?= e((string) ($premium['safe_date_plan'] ?? 'free')) ?></strong> · limite diário: <strong><?= (int) ($premium['safe_date_daily_limit'] ?? 5) ?></strong></p>
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



<div class="row g-3 mt-1">
  <div class="col-lg-12">
    <div class="rd-card"><div class="card-body">
      <?php $diarySummary = $d['diary_summary'] ?? []; $latestDiary = $diarySummary['latest'] ?? null; $diaryCta = $diarySummary['cta'] ?? []; $modeSnapshot = $diarySummary['mode_snapshot'] ?? []; $diaryPrompt = $diarySummary['journey_prompt'] ?? []; ?>
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">Diário do Coração</h6>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-rd-primary" href="/diary/new"><i class="fa-solid fa-pen-to-square me-1"></i><?= e((string) ($diaryCta['action_label'] ?? 'Escrever hoje')) ?></a>
          <a class="btn btn-sm btn-rd-soft" href="/diary"><i class="fa-solid fa-book-heart me-1"></i>Abrir diário</a>
        </div>
      </div>
      <p class="small text-muted mt-2 mb-2">Espaço privado e íntimo. Admin comum não visualiza conteúdo do diário.</p>
      <?php if (($diaryPrompt['message'] ?? '') !== ''): ?><div class="alert alert-info py-2"><span class="small"><strong>Prompt:</strong> <?= e((string) $diaryPrompt['message']) ?></span></div><?php endif; ?>
      <?php if (!empty($latestDiary)): ?>
        <p class="small mb-1">Último registo: <strong><?= e((string) ($latestDiary['title'] ?: 'Entrada sem título')) ?></strong> em <?= e((string) ($latestDiary['created_at'] ?? '')) ?></p>
        <p class="small mb-1">Humor mais recente: <strong><?= e((string) ($diarySummary['recent_mood'] ?? '—')) ?></strong> · Consistência emocional: <strong><?= (int) ($diarySummary['emotional_consistency_signal'] ?? 0) ?>%</strong></p>
        <p class="small mb-1">Entradas 7/30 dias: <strong><?= (int) ($diarySummary['entries_last_7_days'] ?? 0) ?></strong> / <strong><?= (int) ($diarySummary['entries_last_30_days'] ?? 0) ?></strong> · total: <strong><?= (int) ($diarySummary['total_entries'] ?? 0) ?></strong></p>
        <p class="small mb-1">Resumo íntimo recente: <span class="text-muted"><?= e((string) ($diarySummary['last_entry_preview'] ?? 'Sem resumo disponível.')) ?></span></p>
        <p class="small mb-1">Snapshot relacional: intenção <strong><?= e((string) ($modeSnapshot['current_intention'] ?? '—')) ?></strong> · ritmo <strong><?= e((string) ($modeSnapshot['relational_pace'] ?? '—')) ?></strong></p>
        <?php if (!empty($diarySummary['recent_entries'])): ?><p class="small mb-1">Atividade recente:</p><ul class="small mb-1"><?php foreach (($diarySummary['recent_entries'] ?? []) as $entry): ?><li><?= e((string) (($entry['title'] ?? '') !== '' ? $entry['title'] : 'Entrada sem título')) ?> · <?= e((string) ($entry['created_at'] ?? '')) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php if (!empty($diarySummary['mood_distribution_30_days'])): ?><div class="d-flex gap-2 flex-wrap"><?php foreach (($diarySummary['mood_distribution_30_days'] ?? []) as $mood): ?><span class="rd-badge badge-active"><?= e((string) $mood['mood_label']) ?>: <?= (int) $mood['total'] ?></span><?php endforeach; ?></div><?php endif; ?>
      <?php else: ?>
        <p class="small text-muted mb-1 mt-2">Ainda sem entradas. Começa por uma nota breve sobre o que sentes hoje.</p>
      <?php endif; ?>
      <div class="alert alert-light border mt-3 mb-0 py-2"><strong><?= e((string) ($diaryCta['title'] ?? 'Faz o teu check-in emocional de hoje')) ?></strong><br><span class="small"><?= e((string) ($diaryCta['copy'] ?? 'Escrever regularmente dá clareza emocional e melhora tua jornada.')) ?></span></div>
    </div></div>
  </div>
</div>

<?php if (!empty($d['alerts'])): ?>
<div class="rd-card mt-3"><div class="card-body">
  <h6>Alertas importantes</h6>
  <ul class="mb-0"><?php foreach ($d['alerts'] as $alert): ?><li><?= e((string) $alert) ?></li><?php endforeach; ?></ul>
</div></div>
<?php endif; ?>
