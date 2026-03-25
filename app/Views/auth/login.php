<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="rd-card fade-in">
      <div class="card-body p-4">
        <h3 class="mb-3"><i class="fa-solid fa-heart text-danger me-2"></i>Entrar</h3>
        <form method="post" action="/login" class="row g-3">
          <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
          <div class="col-12 input-icon-wrap"><i class="fa-regular fa-envelope"></i><input class="form-control" type="email" name="email" placeholder="Email" required></div>
          <div class="col-12 input-icon-wrap"><i class="fa-solid fa-lock"></i><input class="form-control" type="password" name="password" placeholder="Senha" required></div>
          <div class="col-12 d-grid"><button class="btn btn-rd-primary">Entrar na Conta</button></div>
          <div class="col-12 text-center"><a href="/forgot-password" class="small">Esqueci minha senha</a></div>
        </form>
      </div>
    </div>
  </div>
</div>
