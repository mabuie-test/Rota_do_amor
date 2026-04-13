<?php
$safeDate = $safe_date ?? [];
$history = $safeDate['history'] ?? [];
$feedback = $safeDate['feedback_entries'] ?? [];
$verification = $safeDate['verification_summary'] ?? [];
$risk = $safeDate['risk_signals'] ?? [];
$links = $safeDate['links'] ?? [];
$policy = $safeDate['premium_policy'] ?? [];
?>

<h3 class="mb-3">Investigação institucional · Encontro #<?= (int) ($safeDate['id'] ?? 0) ?></h3>

<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="rd-card"><div class="card-body">
    <h6 class="mb-2">Dados principais</h6>
    <p class="small mb-1"><strong>Título:</strong> <?= e((string) ($safeDate['title'] ?? '')) ?></p>
    <p class="small mb-1"><strong>Status:</strong> <?= e((string) ($safeDate['status'] ?? '')) ?> · <strong>Nível:</strong> <?= e((string) ($safeDate['safety_level'] ?? '')) ?></p>
    <p class="small mb-1"><strong>Local:</strong> <?= e((string) ($safeDate['proposed_location'] ?? '')) ?></p>
    <p class="small mb-1"><strong>Proposto para:</strong> <?= e((string) ($safeDate['proposed_datetime'] ?? '')) ?></p>
    <p class="small mb-1"><strong>Contexto:</strong> match #<?= (int) ($safeDate['match_id'] ?? 0) ?> · conversa #<?= (int) ($safeDate['conversation_id'] ?? 0) ?></p>
    <p class="small mb-0"><strong>Código:</strong> <?= e((string) ($safeDate['confirmation_code'] ?? '')) ?> · <strong>Criado em:</strong> <?= e((string) ($safeDate['created_at'] ?? '')) ?></p>
  </div></div></div>
  <div class="col-lg-4"><div class="rd-card"><div class="card-body">
    <h6 class="mb-2">Ligações operacionais</h6>
    <a class="btn btn-sm btn-outline-primary mb-2" href="<?= e(url((string) ($links['initiator'] ?? '/admin/users'))) ?>">Perfil iniciador</a>
    <a class="btn btn-sm btn-outline-primary mb-2" href="<?= e(url((string) ($links['invitee'] ?? '/admin/users'))) ?>">Perfil convidado</a>
    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= e(url((string) ($links['audit'] ?? '/admin/audit'))) ?>">Auditoria relacionada</a>
    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= e(url((string) ($links['risk'] ?? '/admin/risk'))) ?>">Centro de risco</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url((string) ($links['moderation'] ?? '/admin/moderation'))) ?>">Moderação</a>
  </div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Participantes e confiança</h6>
    <p class="small mb-1">Iniciador: #<?= (int) ($safeDate['initiator_user_id'] ?? 0) ?> · <?= e((string) ($safeDate['initiator_name'] ?? '')) ?> (<?= e((string) ($safeDate['initiator_status'] ?? '')) ?>)</p>
    <p class="small mb-2">Convidado: #<?= (int) ($safeDate['invitee_user_id'] ?? 0) ?> · <?= e((string) ($safeDate['invitee_name'] ?? '')) ?> (<?= e((string) ($safeDate['invitee_status'] ?? '')) ?>)</p>
    <p class="small mb-1">Verificação: iniciador <strong><?= !empty($verification['initiator_verified']) ? 'sim' : 'não' ?></strong> · convidado <strong><?= !empty($verification['invitee_verified']) ? 'sim' : 'não' ?></strong></p>
    <p class="small mb-0">Badges activos: iniciador <?= (int) ($verification['initiator_badges'] ?? 0) ?> · convidado <?= (int) ($verification['invitee_badges'] ?? 0) ?></p>
  </div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Sinais institucionais de risco</h6>
    <p class="small mb-1">Sinal agregado no encontro: <strong><?= e((string) ($risk['high_level'] ?? 'none')) ?></strong></p>
    <p class="small mb-1">Feedback privado com safety_signal: <strong><?= (int) ($risk['private_safety_signals'] ?? 0) ?></strong></p>
    <p class="small mb-2">Eventos auditáveis do encontro: <strong><?= (int) ($risk['audit_events'] ?? 0) ?></strong></p>
    <?php foreach (($risk['users'] ?? []) as $userRisk): ?>
      <div class="small mb-1">Utilizador #<?= (int) ($userRisk['user_id'] ?? 0) ?> · denúncias 30d: <?= (int) ($userRisk['reports_30d'] ?? 0) ?> · bloqueios 30d: <?= (int) ($userRisk['blocks_30d'] ?? 0) ?> · recusas/remarcações/cancelamentos: <?= (int) ($userRisk['declined_30d'] ?? 0) ?>/<?= (int) ($userRisk['rescheduled_30d'] ?? 0) ?>/<?= (int) ($userRisk['cancelled_30d'] ?? 0) ?></div>
    <?php endforeach; ?>
  </div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Pós-encontro e lembretes</h6>
    <p class="small mb-1">Chegada: <?= e((string) ($safeDate['arrived_confirmed_at'] ?? 'não registado')) ?></p>
    <p class="small mb-1">Término em segurança: <?= e((string) ($safeDate['ended_well_confirmed_at'] ?? 'não registado')) ?></p>
    <p class="small mb-1">Lembrete 24h: <?= e((string) ($safeDate['reminder_24h_sent_at'] ?? 'não enviado')) ?></p>
    <p class="small mb-1">Lembrete 2h: <?= e((string) ($safeDate['reminder_2h_sent_at'] ?? 'não enviado')) ?></p>
    <p class="small mb-0">Lembrete mesmo dia: <?= e((string) ($safeDate['reminder_same_day_sent_at'] ?? 'não enviado')) ?></p>
  </div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body">
    <h6>Política premium operacional</h6>
    <p class="small mb-1">Free: até <strong><?= (int) ($policy['free_daily_limit'] ?? 0) ?></strong> propostas/dia e <strong><?= (int) ($policy['max_open_free'] ?? 0) ?></strong> encontros em aberto.</p>
    <p class="small mb-1">Premium: até <strong><?= (int) ($policy['premium_daily_limit'] ?? 0) ?></strong> propostas/dia e <strong><?= (int) ($policy['max_open_premium'] ?? 0) ?></strong> em aberto.</p>
    <p class="small mb-1">premium_guard: <strong><?= !empty($policy['premium_guard_enabled']) ? 'ativo' : 'desativado' ?></strong>.</p>
    <p class="small mb-0">verified_only exige identidade: <strong><?= !empty($policy['verified_only_requires_identity']) ? 'sim' : 'não' ?></strong>.</p>
  </div></div></div>
</div>

<div class="rd-card mb-3"><div class="card-body table-responsive">
  <h6>Cronologia de estados</h6>
  <table class="table table-sm"><thead><tr><th>Quando</th><th>Actor</th><th>De</th><th>Para</th><th>Motivo</th></tr></thead><tbody>
    <?php foreach ($history as $row): ?><tr><td><?= e((string) ($row['created_at'] ?? '')) ?></td><td><?= e((string) (($row['actor_name'] ?? '') !== '' ? $row['actor_name'] : 'sistema')) ?></td><td><?= e((string) ($row['old_status'] ?? '—')) ?></td><td><?= e((string) ($row['new_status'] ?? '')) ?></td><td><?= e((string) ($row['reason'] ?? '—')) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div></div>

<div class="rd-card"><div class="card-body table-responsive">
  <h6>Feedback privado (proporcional e institucional)</h6>
  <table class="table table-sm"><thead><tr><th>Utilizador</th><th>Rating</th><th>Sinal</th><th>Nota de segurança</th><th>Atualizado</th></tr></thead><tbody>
    <?php if ($feedback === []): ?><tr><td colspan="5" class="small text-muted">Sem feedback privado registado.</td></tr><?php endif; ?>
    <?php foreach ($feedback as $row): ?><tr><td>#<?= (int) ($row['user_id'] ?? 0) ?> · <?= e((string) ($row['user_name'] ?? '')) ?></td><td><?= e((string) ($row['rating'] ?? '—')) ?></td><td><?= e((string) ($row['safety_signal'] ?? 'none')) ?></td><td><?= e((string) ($row['safety_note'] ?? '—')) ?></td><td><?= e((string) ($row['updated_at'] ?? '')) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div></div>
