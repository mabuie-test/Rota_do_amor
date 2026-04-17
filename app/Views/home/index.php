<section class="rd-hero-shell p-4 p-md-5 mb-4 fade-in">
  <div class="row align-items-center g-4">
    <div class="col-lg-7">
      <span class="rd-kicker"><i class="fa-solid fa-gem"></i>Luxo emocional com confiança institucional</span>
      <h1 class="display-5 fw-bold mb-3">A nova rota para relações reais em Moçambique</h1>
      <p class="lead mb-4">Uma experiência premium desenhada para intenção relacional, progresso emocional e segurança operacional — sem superficialidade.</p>
      <div class="d-flex flex-wrap gap-2">
        <a href="/register" class="btn btn-rd-primary btn-lg"><i class="fa-solid fa-user-plus me-2"></i>Criar Conta</a>
        <a href="/login" class="btn btn-rd-soft btn-lg"><i class="fa-solid fa-right-to-bracket me-2"></i>Entrar</a>
        <a href="/about" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-circle-info me-2"></i>Como Funciona</a>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="rd-card rd-card-premium h-100">
        <div class="card-body">
          <div class="rd-card-header">
            <div>
              <h5 class="rd-card-header__title"><i class="fa-solid fa-shield-heart"></i>Rede de confiança ativa</h5>
              <p class="rd-card-header__subtitle">Verificação, pagamentos seguros, governança e moderação contínua.</p>
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap mb-2">
            <?php $kind='verified'; $label='Perfis Verificados'; require dirname(__DIR__).'/partials/badge.php'; ?>
            <?php $kind='paid'; $label='Pagamentos Seguros'; require dirname(__DIR__).'/partials/badge.php'; ?>
            <?php $kind='active'; $label='Comunidade Ativa'; require dirname(__DIR__).'/partials/badge.php'; ?>
          </div>
          <p class="rd-supporting-text mb-0">Rota do Amor foi desenhada para conexões com intenção, não interações descartáveis.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="rd-page-header mb-2">
    <div>
      <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-map-signs"></i></span>Jornada da plataforma</h3>
      <p class="rd-page-header__subtitle">Cada etapa foi desenhada para filtrar qualidade relacional e aumentar confiança.</p>
    </div>
  </div>
  <div class="row g-3">
    <?php $steps=[['fa-user-plus','Criar conta'],['fa-circle-check','Verificar email'],['fa-mobile-screen-button','Ativar via M-Pesa'],['fa-compass','Descobrir pessoas'],['fa-heart','Fazer match'],['fa-comments','Conversar com intenção']]; foreach($steps as [$i,$t]): ?>
      <div class="col-6 col-md-4 col-lg-2"><div class="rd-card text-center h-100"><div class="card-body"><span class="rd-step-icon"><i class="fa-solid <?= e($i) ?>"></i></span><p class="small mb-0 mt-2"><?= e($t) ?></p></div></div></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <div class="rd-page-header mb-2">
    <div>
      <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-crown"></i></span>Modelo premium</h3>
      <p class="rd-page-header__subtitle">Camadas de valor claras para ativação, progressão e destaque.</p>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><div class="rd-card"><div class="card-body"><h6><i class="fa-solid fa-key me-2"></i>Ativação Inicial</h6><h4>100 MZN</h4><p class="text-muted">Pagamento único para desbloquear a conta.</p></div></div></div>
    <div class="col-md-4"><div class="rd-card"><div class="card-body"><h6><i class="fa-solid fa-calendar-check me-2"></i>Subscrição Mensal</h6><h4>40 MZN</h4><p class="text-muted">Acesso completo a descoberta, swipe e mensagens.</p></div></div></div>
    <div class="col-md-4"><div class="rd-card"><div class="card-body"><h6><i class="fa-solid fa-fire-flame-curved me-2"></i>Boost Premium</h6><h4>25 MZN</h4><p class="text-muted">Mais visibilidade no momento certo da sua jornada.</p></div></div></div>
  </div>
</section>

<section class="rd-card">
  <div class="card-body text-center py-5">
    <h3 class="mb-2">Pronto(a) para começar sua rota?</h3>
    <p class="text-muted mb-3">Entre numa plataforma feita para relações verdadeiras, seguras e maduras.</p>
    <a href="/register" class="btn btn-rd-primary btn-lg"><i class="fa-solid fa-heart me-2"></i>Quero me registar</a>
  </div>
</section>
