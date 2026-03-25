<div class="row g-3">
  <div class="col-lg-7"><div class="rd-card"><div class="card-body p-4">
    <h4><i class="fa-solid fa-mobile-screen-button me-2 text-primary"></i>Ativação da Conta</h4>
    <p class="text-muted">Conclua o pagamento de ativação para desbloquear completamente a plataforma.</p>
    <ul class="list-unstyled small">
      <li><i class="fa-solid fa-money-bill-wave me-2 text-success"></i>Valor de ativação: <strong>100 MZN</strong></li>
      <li><i class="fa-solid fa-circle-check me-2 text-success"></i>Pagamento via M-Pesa (Débito API)</li>
      <li><i class="fa-solid fa-clock me-2 text-warning"></i>Status em tempo real disponível na rota de status</li>
    </ul>
    <form method="post" action="/activation/pay" class="mt-3">
      <div class="input-icon-wrap mb-2"><i class="fa-solid fa-phone"></i><input class="form-control" name="phone" placeholder="25884XXXXXXX" required></div>
      <button class="btn btn-rd-primary"><i class="fa-solid fa-bolt me-2"></i>Iniciar Pagamento</button>
    </form>
  </div></div></div>
  <div class="col-lg-5"><div class="rd-card"><div class="card-body p-4">
    <h6>Status do processo</h6>
    <p><?php $kind='pending'; $label='Pendente'; require dirname(__DIR__).'/partials/badge.php'; ?></p>
    <p class="small text-muted mb-0">Após confirmação do gateway, sua conta muda automaticamente para ativa.</p>
  </div></div></div>
</div>
