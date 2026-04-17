<div class="row justify-content-center">
  <div class="col-lg-5">
    <div class="rd-card rd-auth-card fade-in">
      <div class="card-body p-4">
        <span class="rd-kicker mb-2"><i class="fa-solid fa-heart-circle-check"></i>Área segura</span>
        <h3 class="mb-3"><i class="fa-solid fa-heart-pulse text-danger me-2"></i>Entrar</h3>
        <p class="text-muted small mb-3">Aceda à sua experiência premium de conexões reais com proteção ativa.</p>
        <form method="post" action="/login" class="row g-3" data-enhanced-submit><?= csrf_field() ?>
          <div class="col-12 input-icon-wrap"><i class="fa-regular fa-envelope"></i><input class="form-control" type="email" name="email" placeholder="Email" required></div>
          <div class="col-12 input-icon-wrap"><i class="fa-solid fa-lock"></i><input class="form-control" type="password" name="password" placeholder="Senha" required></div>
          <div class="col-12 d-grid"><button class="btn btn-rd-primary">Entrar na Conta</button></div>
          <div class="col-12 text-center"><a href="/forgot-password" class="small">Esqueci minha senha</a></div>
          <div class="col-12 text-center">
            <p class="small text-muted mb-0">Não tens uma conta? <a href="/register" class="fw-semibold text-decoration-none">Cria a tua conta</a></p>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
