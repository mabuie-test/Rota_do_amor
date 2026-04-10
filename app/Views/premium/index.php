<h3 class="mb-3"><i class="fa-solid fa-crown me-2 text-warning"></i>Premium & Boost</h3>
<div class="row g-3">
  <div class="col-lg-6"><div class="rd-card"><div class="card-body"><h5>Subscrição</h5><p class="text-muted">Acesso completo aos recursos sociais e de descoberta.</p><a href="/subscription/status" class="btn btn-rd-soft">Ver estado</a></div></div></div>
  <div class="col-lg-6"><div class="rd-card"><div class="card-body"><h5>Boost de Perfil</h5><form method="post" action="/premium/boost/pay"><?= csrf_field() ?><div class="input-icon-wrap mb-2"><i class="fa-solid fa-phone"></i><input class="form-control" name="phone" placeholder="25884XXXXXXX"></div><button class="btn btn-rd-primary"><i class="fa-solid fa-bolt me-2"></i>Ativar Boost</button></form></div></div></div>
</div>
