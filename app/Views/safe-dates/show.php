<?php
$safeDate = $safe_date ?? [];
$history = $safeDate['history'] ?? [];
$status = (string) ($safeDate['status'] ?? 'proposed');
$feedback = $safeDate['private_feedback'] ?? [];
$statusLabels = [
    'proposed' => 'Proposto',
    'accepted' => 'Aceite',
    'declined' => 'Recusado',
    'cancelled' => 'Cancelado',
    'reschedule_requested' => 'Remarcação pendente',
    'rescheduled' => 'Remarcado',
    'completed' => 'Concluído',
    'expired' => 'Expirado',
];
?>
<h3 class="mb-3"><i class="fa-solid fa-shield-heart me-2"></i>Detalhe do Encontro Seguro #<?= (int) ($safeDate['id'] ?? 0) ?></h3>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="rd-card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h5 class="mb-1"><?= e((string) ($safeDate['title'] ?? 'Encontro Seguro')) ?></h5>
          <p class="small text-muted mb-1">com <strong><?= e((string) ($safeDate['counterpart_name'] ?? '')) ?></strong> · tipo <?= e((string) ($safeDate['meeting_type'] ?? '—')) ?></p>
          <p class="small text-muted mb-1">Local: <?= e((string) ($safeDate['proposed_location'] ?? '')) ?></p>
          <p class="small text-muted mb-1">Data/Hora ativa: <?= e((string) ($safeDate['proposed_datetime'] ?? '')) ?></p>
          <?php if (!empty($safeDate['reschedule_proposed_datetime']) && $status === 'reschedule_requested'): ?>
            <p class="small text-warning mb-1">Nova data sugerida: <strong><?= e((string) $safeDate['reschedule_proposed_datetime']) ?></strong></p>
          <?php endif; ?>
          <p class="small text-muted mb-1">Segurança: <?= e((string) ($safeDate['safety_level'] ?? 'standard')) ?> · código: <strong><?= e((string) ($safeDate['confirmation_code'] ?? '—')) ?></strong></p>
          <?php if (!empty($safeDate['note'])): ?><p class="small mb-0">Nota: <?= e((string) $safeDate['note']) ?></p><?php endif; ?>
        </div>
        <span class="rd-badge badge-active"><?= e((string) ($statusLabels[$status] ?? $status)) ?></span>
      </div>

      <hr>
      <div class="alert alert-light border small">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        Prioriza locais públicos, transporte seguro e comunica um contacto de confiança antes do encontro.
      </div>

      <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($safeDate['can_accept'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/accept"><?= csrf_field() ?><button class="btn btn-sm btn-success"><?= $status === 'reschedule_requested' ? 'Aceitar remarcação' : 'Aceitar' ?></button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_decline'])): ?>
          <form method="post" action="<?= $status === 'reschedule_requested' ? '/dates/' . (int) $safeDate['id'] . '/reschedule/respond' : '/dates/' . (int) $safeDate['id'] . '/decline' ?>" class="d-flex gap-2">
            <?= csrf_field() ?>
            <?php if ($status === 'reschedule_requested'): ?><input type="hidden" name="accept" value="0"><?php endif; ?>
            <input class="form-control form-control-sm" name="reason" placeholder="Motivo opcional"><button class="btn btn-sm btn-outline-danger"><?= $status === 'reschedule_requested' ? 'Recusar remarcação' : 'Recusar' ?></button>
          </form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_cancel'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/cancel" class="d-flex gap-2"><?= csrf_field() ?><input class="form-control form-control-sm" name="reason" placeholder="Motivo opcional"><button class="btn btn-sm btn-outline-secondary">Cancelar</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_reschedule'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/reschedule" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="datetime-local" name="proposed_datetime" class="form-control form-control-sm" required><input class="form-control form-control-sm" name="reason" placeholder="Justificativa"><button class="btn btn-sm btn-rd-primary">Solicitar remarcação</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_mark_arrived'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/arrived"><?= csrf_field() ?><button class="btn btn-sm btn-outline-info">Cheguei bem</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_mark_finished_well'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/finished-well"><?= csrf_field() ?><button class="btn btn-sm btn-outline-info">Terminei bem</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_complete'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/complete"><?= csrf_field() ?><button class="btn btn-sm btn-outline-success">Marcar como concluído</button></form>
        <?php endif; ?>
      </div>

      <hr>
      <h6 class="mb-2">Feedback privado pós-encontro</h6>
      <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/feedback" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-3"><select name="rating" class="form-select form-select-sm"><option value="">Avaliação</option><?php for ($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= (int) ($feedback['rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?>/5</option><?php endfor; ?></select></div>
        <div class="col-md-4"><select name="safety_signal" class="form-select form-select-sm"><option value="none">Sem sinal de risco</option><option value="attention" <?= ($feedback['safety_signal'] ?? '') === 'attention' ? 'selected' : '' ?>>Atenção</option><option value="emergency" <?= ($feedback['safety_signal'] ?? '') === 'emergency' ? 'selected' : '' ?>>Emergência</option></select></div>
        <div class="col-12"><textarea name="feedback_note" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Feedback privado (não visível para o outro utilizador)"><?= e((string) ($feedback['feedback_note'] ?? '')) ?></textarea></div>
        <div class="col-12"><textarea name="safety_note" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Detalhe de segurança (privado para moderação/sistemas de risco)"><?= e((string) ($feedback['safety_note'] ?? '')) ?></textarea></div>
        <div class="col-12"><button class="btn btn-sm btn-rd-primary">Guardar feedback privado</button></div>
      </form>
    </div></div>
  </div>

  <div class="col-lg-5">
    <div class="rd-card"><div class="card-body">
      <h6>Confiança do perfil</h6>
      <p class="small mb-1">Conta: <?= e((string) ($safeDate['counterpart_status'] ?? '—')) ?></p>
      <p class="small mb-1">Identidade verificada: <?= !empty($safeDate['counterpart_verified']) ? 'Sim' : 'Não' ?></p>
      <p class="small mb-3">Badges ativos: <?= (int) ($safeDate['counterpart_badges'] ?? 0) ?></p>
      <a class="btn btn-sm btn-rd-soft" href="/messages?conversation=<?= (int) ($safeDate['conversation_id'] ?? 0) ?>">Abrir conversa</a>
      <a class="btn btn-sm btn-outline-primary" href="/dates">Voltar à lista</a>
    </div></div>

    <div class="rd-card mt-3"><div class="card-body">
      <h6>Histórico de estados</h6>
      <?php if ($history === []): ?>
        <p class="small text-muted mb-0">Sem histórico adicional.</p>
      <?php else: ?>
        <ul class="small mb-0 ps-3">
          <?php foreach ($history as $event): ?>
            <li>
              <strong><?= e((string) ($statusLabels[(string) ($event['new_status'] ?? '')] ?? ($event['new_status'] ?? ''))) ?></strong>
              por <?= e((string) ($event['actor_name'] ?? 'sistema')) ?>
              em <?= e((string) ($event['created_at'] ?? '')) ?>
              <?php if (!empty($event['reason'])): ?>· <?= e((string) $event['reason']) ?><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>
