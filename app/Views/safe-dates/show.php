<?php
$safeDate = $safe_date ?? [];
$history = $safeDate['history'] ?? [];
$status = (string) ($safeDate['status'] ?? 'proposed');
$statusLabels = [
    'proposed' => 'Proposto',
    'accepted' => 'Aceite',
    'declined' => 'Recusado',
    'cancelled' => 'Cancelado',
    'reschedule_requested' => 'Remarcação pedida',
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
          <p class="small text-muted mb-1">Data/Hora: <?= e((string) ($safeDate['proposed_datetime'] ?? '')) ?></p>
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
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/accept"><?= csrf_field() ?><button class="btn btn-sm btn-success">Aceitar</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_decline'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/decline" class="d-flex gap-2"><?= csrf_field() ?><input class="form-control form-control-sm" name="reason" placeholder="Motivo opcional"><button class="btn btn-sm btn-outline-danger">Recusar</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_cancel'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/cancel" class="d-flex gap-2"><?= csrf_field() ?><input class="form-control form-control-sm" name="reason" placeholder="Motivo opcional"><button class="btn btn-sm btn-outline-secondary">Cancelar</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_reschedule'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/reschedule" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="datetime-local" name="proposed_datetime" class="form-control form-control-sm" required><input class="form-control form-control-sm" name="reason" placeholder="Justificativa"><button class="btn btn-sm btn-rd-primary">Remarcar</button></form>
        <?php endif; ?>
        <?php if (!empty($safeDate['can_complete'])): ?>
          <form method="post" action="/dates/<?= (int) $safeDate['id'] ?>/complete"><?= csrf_field() ?><button class="btn btn-sm btn-outline-success">Marcar como concluído</button></form>
        <?php endif; ?>
      </div>
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
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>
