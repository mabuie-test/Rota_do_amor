<h3 class="mb-3">Estado da Subscrição</h3>
<div class="row g-3">
  <div class="col-md-6"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Dias restantes</div><div class="value"><?= (int)($subscription['days_remaining'] ?? 0) ?></div></div></div></div>
  <div class="col-md-6"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Estado</div><div class="value"><?= !empty($subscription['has_active_subscription']) ? 'Ativa' : 'Expirada' ?></div></div></div></div>
</div>
<form method="post" action="/subscription/renew" class="rd-card mt-3"><?= csrf_field() ?><div class="card-body"><h6>Renovar agora</h6><div class="input-icon-wrap mb-2"><i class="fa-solid fa-phone"></i><input class="form-control" name="phone" placeholder="25884XXXXXXX"></div><button class="btn btn-rd-primary">Renovar com M-Pesa</button></div></form>
