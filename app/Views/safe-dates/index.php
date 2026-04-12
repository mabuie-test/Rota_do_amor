<?php
$items = $items ?? [];
$scope = $scope ?? 'upcoming';
$prefillInvitee = (int) ($prefill_invitee_id ?? 0);
$statusLabels = [
    'proposed' => 'Proposto',
    'accepted' => 'Aceite',
    'declined' => 'Recusado',
    'cancelled' => 'Cancelado',
    'reschedule_requested' => 'Remarcação pendente',
    'rescheduled' => 'Remarcação confirmada',
    'completed' => 'Concluído',
    'expired' => 'Expirado',
];
?>
<h3 class="mb-3"><i class="fa-solid fa-shield-heart me-2"></i>Encontro Seguro</h3>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="rd-card h-100"><div class="card-body">
      <h6 class="mb-2">Propor encontro</h6>
      <p class="small text-muted">Use esta proposta para avançar do online para o real com regras de segurança, confirmação e histórico auditável.</p>
      <form method="post" action="/dates/propose" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12"><label class="form-label small">ID da pessoa</label><input name="invitee_user_id" class="form-control" required type="number" min="1" value="<?= $prefillInvitee > 0 ? $prefillInvitee : '' ?>"></div>
        <div class="col-12"><label class="form-label small">Título</label><input name="title" class="form-control" maxlength="160" placeholder="Ex.: Café no fim de tarde" required></div>
        <div class="col-md-6"><label class="form-label small">Tipo</label><select name="meeting_type" class="form-select"><option value="coffee">Café</option><option value="lunch">Almoço</option><option value="dinner">Jantar</option><option value="walk">Passeio</option><option value="event">Evento</option><option value="video_call">Vídeo chamada</option><option value="other">Outro</option></select></div>
        <div class="col-md-6"><label class="form-label small">Nível de segurança</label><select name="safety_level" class="form-select"><option value="standard">Standard</option><option value="verified_only">Apenas verificados</option><option value="premium_guard">Premium Guard</option></select></div>
        <div class="col-12"><label class="form-label small">Local proposto</label><input name="proposed_location" class="form-control" maxlength="255" placeholder="Local público recomendado" required></div>
        <div class="col-12"><label class="form-label small">Data e hora</label><input name="proposed_datetime" class="form-control" type="datetime-local" required></div>
        <div class="col-12"><label class="form-label small">Nota</label><textarea name="note" class="form-control" maxlength="500" rows="2" placeholder="Mensagem opcional"></textarea></div>
        <div class="col-12"><button class="btn btn-rd-primary w-100">Propor Encontro Seguro</button></div>
      </form>
      <div class="alert alert-light border small mt-3 mb-0">
        <strong>Checklist de segurança:</strong>
        <ul class="mb-0 mt-1">
          <li>Escolha local público e horário seguro.</li>
          <li>Partilhe com alguém de confiança o plano do encontro.</li>
          <li>Confirme identidade e sinais de confiança antes de aceitar.</li>
        </ul>
      </div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="rd-card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Os teus encontros</h6>
        <div class="btn-group btn-group-sm">
          <a class="btn <?= $scope === 'upcoming' ? 'btn-rd-primary' : 'btn-outline-secondary' ?>" href="/dates?scope=upcoming">Próximos</a>
          <a class="btn <?= $scope === 'history' ? 'btn-rd-primary' : 'btn-outline-secondary' ?>" href="/dates?scope=history">Histórico</a>
          <a class="btn <?= $scope === 'all' ? 'btn-rd-primary' : 'btn-outline-secondary' ?>" href="/dates?scope=all">Todos</a>
        </div>
      </div>
      <?php if ($items === []): ?>
        <div class="rd-empty p-4">Nenhum encontro encontrado neste filtro.</div>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <a href="/dates/<?= (int) $item['id'] ?>" class="text-decoration-none text-reset d-block border rounded-4 p-3 mb-2 rd-invite-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <strong><?= e((string) ($item['title'] ?? 'Encontro Seguro')) ?></strong>
                <div class="small text-muted">com <?= e((string) ($item['counterpart_name'] ?? '—')) ?> · <?= e((string) ($item['proposed_location'] ?? '')) ?></div>
                <div class="small text-muted">Data ativa: <?= e((string) ($item['proposed_datetime'] ?? '')) ?> · Segurança: <?= e((string) ($item['safety_level'] ?? 'standard')) ?></div>
              </div>
              <span class="rd-badge badge-active"><?= e((string) ($statusLabels[(string) ($item['status'] ?? '')] ?? ($item['status'] ?? ''))) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div></div>
  </div>
</div>
