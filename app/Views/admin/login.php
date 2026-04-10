<div class="row justify-content-center"><div class="col-lg-4"><div class="rd-card"><div class="card-body p-4">
  <h4 class="mb-3"><i class="fa-solid fa-shield-halved me-2"></i>Painel Admin</h4>
  <form method="post" action="/admin/login" class="d-flex flex-column gap-2"><?= csrf_field() ?>
    <input class="form-control" type="email" name="email" placeholder="Email admin">
    <input class="form-control" type="password" name="password" placeholder="Senha">
    <button class="btn btn-rd-primary">Entrar</button>
  </form>
</div></div></div></div>
