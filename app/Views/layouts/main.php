<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Rota do Amor') ?></title>
    <meta name="description" content="Rota do Amor: plataforma premium para conexões reais em Moçambique.">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    >
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    >
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

    <noscript>
      <style>.rd-feed-floating-cta{display:none!important;}</style>
    </noscript>
</head>
<?php
use App\Core\Auth;
use App\Core\Flash;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$unreadNotifications = (int) ($layout_unread_notifications ?? 0);
$isAdminArea = str_starts_with($path, '/admin') && $path !== '/admin/login';
$isAuthenticated = Auth::check();
$experienceClass = $isAdminArea ? 'rd-experience-admin' : ($isAuthenticated ? 'rd-experience-app' : 'rd-experience-public');

$publicNavItems = [
    '/' => ['label' => 'Início', 'icon' => 'fa-house'],
    '/plans' => ['label' => 'Planos', 'icon' => 'fa-gem'],
    '/about' => ['label' => 'Sobre', 'icon' => 'fa-circle-info'],
    '/safety' => ['label' => 'Segurança', 'icon' => 'fa-shield-heart'],
    '/login' => ['label' => 'Entrar', 'icon' => 'fa-right-to-bracket'],
    '/register' => ['label' => 'Registar', 'icon' => 'fa-user-plus'],
];

$appNavItems = [
    '/dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-house', 'group' => 'Core'],
    '/discover' => ['label' => 'Descobrir', 'icon' => 'fa-compass', 'group' => 'Conexões'],
    '/swipe' => ['label' => 'Swipe', 'icon' => 'fa-bolt', 'group' => 'Conexões'],
    '/matches' => ['label' => 'Matches', 'icon' => 'fa-stars', 'group' => 'Conexões'],
    '/messages' => ['label' => 'Mensagens', 'icon' => 'fa-comments', 'group' => 'Social'],
    '/feed' => ['label' => 'Feed', 'icon' => 'fa-sparkles', 'group' => 'Social'],
    '/stories/anonymous' => ['label' => 'Histórias', 'icon' => 'fa-feather-pointed', 'group' => 'Social'],
    '/notifications' => ['label' => 'Notificações', 'icon' => 'fa-bell', 'group' => 'Social'],
    '/dates' => ['label' => 'Encontro Seguro', 'icon' => 'fa-shield-heart', 'group' => 'Segurança'],
    '/invites/received' => ['label' => 'Gostaram de Mim', 'icon' => 'fa-envelope-open-heart', 'group' => 'Segurança'],
    '/visitors' => ['label' => 'Visitantes', 'icon' => 'fa-eye', 'group' => 'Segurança'],
    '/compatibility-duel' => ['label' => 'Duelo', 'icon' => 'fa-people-arrows', 'group' => 'Engajamento'],
    '/diary' => ['label' => 'Diário', 'icon' => 'fa-book-heart', 'group' => 'Engajamento'],
    '/profile' => ['label' => 'Perfil', 'icon' => 'fa-user-pen', 'group' => 'Conta'],
];

$showShellSidebar = $isAuthenticated || $isAdminArea;
$sidebarView = $isAdminArea ? dirname(__DIR__) . '/partials/admin-sidebar.php' : dirname(__DIR__) . '/partials/app-sidebar.php';
?>
<body class="<?= e($experienceClass) ?>" data-auth-user-id="<?= (int) (Auth::id() ?? 0) ?>">
<a href="#main-content" class="rd-skip-link">Ir para conteúdo principal</a>

<div class="rd-topbar py-2">
  <div class="container d-flex justify-content-between align-items-center gap-2 flex-wrap">
    <span><i class="fa-solid fa-route me-2"></i>Rota do Amor · design system premium e experiência mobile-first.</span>
    <a href="<?= e(url('plans')) ?>" class="text-decoration-none fw-semibold"><i class="fa-solid fa-gem me-1"></i>Planos & benefícios</a>
  </div>
</div>

<header class="rd-header">
  <div class="container rd-header__bar">
    <div class="d-flex align-items-center gap-2">
      <?php if ($showShellSidebar): ?>
        <button
          class="rd-shell-toggle d-lg-none"
          type="button"
          id="rdSidebarToggle"
          data-rd-sidebar-toggle
          aria-controls="rdMobileNav"
          aria-expanded="false"
          aria-label="Abrir menu principal"
        >
          <i class="fa-solid fa-bars"></i>
        </button>
      <?php endif; ?>
      <a class="rd-brand" href="<?= e(url()) ?>">
        <span class="rd-brand__logo"><i class="fa-solid fa-heart"></i></span>
        <span>Rota do Amor</span>
      </a>
    </div>

    <?php if (!$showShellSidebar): ?>
      <nav class="rd-public-nav" aria-label="Navegação pública">
        <?php foreach ($publicNavItems as $href => $item): ?>
          <?php $active = $href === '/' ? $path === '/' : str_starts_with($path, $href); ?>
          <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= e(url($href)) ?>">
            <i class="fa-solid <?= e($item['icon']) ?> me-1 rd-icon-sm"></i><?= e($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <div class="d-flex align-items-center gap-2">
        <?php if (!$isAdminArea): ?>
          <a class="btn btn-sm btn-rd-soft d-none d-md-inline-flex" href="<?= e(url('/profile')) ?>"><i class="fa-solid fa-user me-1"></i>Minha conta</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/logout')) ?>" class="m-0">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-right-from-bracket me-1"></i>Sair</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</header>

<?php if ($showShellSidebar): ?>
  <div class="rd-mobile-nav" id="rdMobileNav" hidden>
    <div class="rd-mobile-nav__backdrop" data-rd-sidebar-close></div>
    <aside class="rd-mobile-nav__panel" aria-label="Menu lateral" aria-modal="true" role="dialog">
      <div class="rd-mobile-nav__header">
        <h5 class="rd-mobile-nav__title mb-0">Menu</h5>
        <button type="button" class="btn-close" aria-label="Fechar" data-rd-sidebar-close></button>
      </div>
      <div class="rd-mobile-nav__content">
        <?php $sidebarMode = 'mobile'; require $sidebarView; ?>
      </div>
    </aside>
  </div>
<?php endif; ?>

<main class="container main-shell" id="main-content">
  <?php if ($message = Flash::get('success')): ?>
    <?php $type = 'success'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if ($message = Flash::get('error')): ?>
    <?php $type = 'danger'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if ($message = Flash::get('warning')): ?>
    <?php $type = 'warning'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>

  <?php if ($showShellSidebar): ?>
    <div class="rd-shell">
      <div class="rd-shell__sidebar d-none d-lg-block">
        <?php $sidebarMode = 'desktop'; require $sidebarView; ?>
      </div>
      <section class="rd-content fade-in"><?php require $file; ?></section>
    </div>
  <?php else: ?>
    <section class="fade-in"><?php require $file; ?></section>
  <?php endif; ?>
</main>

<footer class="rd-footer py-5 mt-4">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
    <div>
      <strong class="d-block mb-1"><i class="fa-solid fa-heart me-2"></i>Rota do Amor</strong>
      <small>Plataforma social premium com design sistémico, navegação lateral moderna e base visual unificada.</small>
    </div>
    <div class="d-flex gap-3 align-items-center flex-wrap">
      <a href="<?= e(url('plans')) ?>" class="text-decoration-none text-light">Planos</a>
      <a href="<?= e(url('about')) ?>" class="text-decoration-none text-light">Sobre</a>
      <a href="<?= e(url('safety')) ?>" class="text-decoration-none text-light">Segurança</a>
      <a href="#" class="text-decoration-none text-light" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
      <a href="#" class="text-decoration-none text-light" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
    </div>
  </div>
</footer>

<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
  crossorigin="anonymous"
  defer
></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
