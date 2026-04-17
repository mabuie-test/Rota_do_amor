<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Rota do Amor') ?></title>
    <meta name="description" content="Rota do Amor: plataforma premium para conexões reais em Moçambique.">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
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
?>
<body class="<?= e($experienceClass) ?>">
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
        <button class="btn btn-sm btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#rdMobileSidebar" aria-label="Abrir menu">
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
  <div class="offcanvas offcanvas-start" tabindex="-1" id="rdMobileSidebar" aria-labelledby="rdMobileSidebarLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="rdMobileSidebarLabel">Menu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
      <?php if ($isAdminArea): ?>
        <?php require dirname(__DIR__) . '/partials/admin-sidebar.php'; ?>
      <?php else: ?>
        <?php require dirname(__DIR__) . '/partials/app-sidebar.php'; ?>
      <?php endif; ?>
    </div>
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
      <div class="d-none d-lg-block">
        <?php if ($isAdminArea): ?>
          <?php require dirname(__DIR__) . '/partials/admin-sidebar.php'; ?>
        <?php else: ?>
          <?php require dirname(__DIR__) . '/partials/app-sidebar.php'; ?>
        <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
