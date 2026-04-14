<section class="hero p-4 p-md-5 mb-4 fade-in">
  <div class="row align-items-center g-4">
    <div class="col-lg-7">
      <div class="hero-gradient">
        <h1 class="display-5 fw-bold mb-3">Encontre conexões verdadeiras em Moçambique</h1>
        <p class="lead mb-4">Uma plataforma premium para amizades, namoro e casamento com segurança, verificação e tecnologia de compatibilidade.</p>
        <div class="d-flex flex-wrap gap-2">
          <a href="/register" class="btn btn-light btn-lg"><i class="fa-solid fa-user-plus me-2"></i>Criar Conta</a>
          <a href="/login" class="btn btn-outline-light btn-lg"><i class="fa-solid fa-right-to-bracket me-2"></i>Entrar</a>
          <a href="/about" class="btn btn-outline-light btn-lg">Como Funciona</a>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="rd-card h-100">
        <div class="card-body">
          <h5><i class="fa-solid fa-shield-heart me-2 text-danger"></i>Confiança e segurança</h5>
          <p class="text-muted">Verificação por email, ativação M-Pesa e moderação ativa para uma comunidade saudável.</p>
          <div class="d-flex gap-2 flex-wrap">
            <?php $kind='verified'; $label='Perfis Verificados'; require dirname(__DIR__).'/partials/badge.php'; ?>
            <?php $kind='paid'; $label='Pagamentos Seguros'; require dirname(__DIR__).'/partials/badge.php'; ?>
            <?php $kind='active'; $label='Comunidade Ativa'; require dirname(__DIR__).'/partials/badge.php'; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <h3 class="mb-3">Como funciona</h3>
  <div class="row g-3">
    <?php $steps=[['fa-user-plus','Criar conta'],['fa-circle-check','Verificar email'],['fa-mobile-screen-button','Ativar via M-Pesa'],['fa-compass','Descobrir pessoas'],['fa-heart','Fazer match'],['fa-comments','Conversar']]; foreach($steps as [$i,$t]): ?>
      <div class="col-6 col-md-4 col-lg-2"><div class="rd-card text-center h-100"><div class="card-body"><i class="fa-solid <?= e($i) ?> mb-2 text-primary"></i><p class="small mb-0"><?= e($t) ?></p></div></div></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <h3 class="mb-3">Planos e preços</h3>
  <div class="row g-3">
    <div class="col-md-4"><div class="rd-card"><div class="card-body"><h6>Ativação Inicial</h6><h4>100 MZN</h4><p class="text-muted">Pagamento único para ativar a conta.</p></div></div></div>
    <div class="col-md-4"><div class="rd-card"><div class="card-body"><h6>Subscrição Mensal</h6><h4>40 MZN</h4><p class="text-muted">Acesso completo a descoberta, swipe e chat.</p></div></div></div>
    <div class="col-md-4"><div class="rd-card"><div class="card-body"><h6>Boost Premium</h6><h4>25 MZN</h4><p class="text-muted">Mais visibilidade por tempo limitado.</p></div></div></div>
  </div>
</section>

<section class="mb-4">
  <h3 class="mb-3">FAQ</h3>
  <div class="accordion" id="faqHome">
    <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#f1">Como ativo a conta?</button></h2><div id="f1" class="accordion-collapse collapse show"><div class="accordion-body">Após registo e verificação de email, faça o pagamento de ativação via M-Pesa.</div></div></div>
    <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f2">Quando posso conversar?</button></h2><div id="f2" class="accordion-collapse collapse"><div class="accordion-body">Com subscrição ativa e seguindo as regras de match definidas na plataforma.</div></div></div>
  </div>
</section>

<section class="rd-card">
  <div class="card-body text-center py-5">
    <h3 class="mb-2">Pronto(a) para começar sua rota?</h3>
    <p class="text-muted mb-3">Crie sua conta agora e encontre conexões reais.</p>
    <a href="/register" class="btn btn-rd-primary btn-lg"><i class="fa-solid fa-heart me-2"></i>Quero me registar</a>
  </div>
</section>
