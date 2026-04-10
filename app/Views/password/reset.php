<div class="row justify-content-center"><div class="col-lg-5"><div class="rd-card"><div class="card-body p-4">
  <h4><i class="fa-solid fa-lock me-2"></i>Redefinir senha</h4>
  <form method="post" action="/reset-password"><?= csrf_field() ?>
    <input class="form-control mb-2" name="token" value="<?= e((string) ($token ?? '')) ?>" placeholder="Token">
    <div class="input-icon-wrap mb-3"><i class="fa-solid fa-lock"></i><input class="form-control" type="password" name="password" placeholder="Nova senha" required></div>
    <button class="btn btn-rd-primary w-100">Atualizar senha</button>
  </form>
</div></div></div></div>
