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
use App\Core\Flash;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$unreadNotifications = (int) ($layout_unread_notifications ?? 0);
$isAdminArea = str_starts_with($path, '/admin') && $path !== '/admin/login';
$moduleClass = 'module-generic';
if (str_starts_with($path, '/discover') || str_starts_with($path, '/swipe')) { $moduleClass = 'module-discover'; }
elseif (str_starts_with($path, '/messages')) { $moduleClass = 'module-messages'; }
elseif (str_starts_with($path, '/invites')) { $moduleClass = 'module-intention'; }
elseif (str_starts_with($path, '/dates')) { $moduleClass = 'module-safety'; }
elseif (str_starts_with($path, '/diary')) { $moduleClass = 'module-diary'; }
elseif (str_starts_with($path, '/feed') || str_starts_with($path, '/stories') || str_starts_with($path, '/visitors') || str_starts_with($path, '/compatibility-duel') || str_starts_with($path, '/daily-route')) { $moduleClass = 'module-retention'; }
elseif ($isAdminArea) { $moduleClass = 'module-admin'; }

$navItems = [
    '/dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-house'],
    '/discover' => ['label' => 'Descobrir', 'icon' => 'fa-compass'],
    '/swipe' => ['label' => 'Swipe', 'icon' => 'fa-bolt'],
    '/matches' => ['label' => 'Matches', 'icon' => 'fa-stars'],
    '/messages' => ['label' => 'Mensagens', 'icon' => 'fa-comments'],
    '/dates' => ['label' => 'Encontro Seguro', 'icon' => 'fa-shield-heart'],
    '/invites/received' => ['label' => 'Gostaram de Mim', 'icon' => 'fa-envelope-open-heart'],
    '/feed' => ['label' => 'Feed', 'icon' => 'fa-sparkles'],
    '/stories/anonymous' => ['label' => 'Histórias', 'icon' => 'fa-feather-pointed'],
    '/visitors' => ['label' => 'Visitantes', 'icon' => 'fa-eye'],
    '/compatibility-duel' => ['label' => 'Duelo', 'icon' => 'fa-people-arrows'],
    '/notifications' => ['label' => 'Notificações', 'icon' => 'fa-circle-check'],
    '/diary' => ['label' => 'Diário', 'icon' => 'fa-book-heart'],
    '/profile' => ['label' => 'Perfil', 'icon' => 'fa-heart-pulse'],
];
?>
<body class="<?= e($moduleClass) ?> <?= $isAdminArea ? 'is-admin-area' : '' ?>">
<a href="#main-content" class="rd-skip-link">Ir para conteúdo principal</a>
<div class="rd-topbar py-2">
  <div class="container d-flex justify-content-between align-items-center small gap-2 flex-wrap">
    <span><i class="fa-solid fa-route me-2"></i>Rota do Amor 2.0 · jornada relacional premium para Moçambique urbano.</span>
    <a href="<?= e(url('plans')) ?>" class="text-decoration-none text-white fw-semibold"><i class="fa-solid fa-gem me-1"></i>Planos & benefícios</a>
  </div>
</div>

<nav class="navbar navbar-expand-lg navbar-dark rd-navbar sticky-top">
  <div class="container py-2">
    <a class="navbar-brand" href="<?= e(url()) ?>"><i class="fa-solid fa-compass-drafting me-2"></i>Rota do Amor</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <div class="navbar-nav ms-auto gap-1 rd-nav-grid">
        <?php foreach ($navItems as $href => $item): ?>
          <?php $active = str_starts_with($path, $href) ? 'active' : ''; ?>
          <a class="nav-link <?= e($active) ?>" href="<?= e(url($href)) ?>"><i class="fa-solid <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?><?php if ($href === '/notifications' && $unreadNotifications > 0): ?><span class="badge text-bg-light ms-1"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</nav>

<main class="container main-shell py-4 py-md-5" id="main-content">
  <?php if ($message = Flash::get('success')): ?>
    <?php $type = 'success'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if ($message = Flash::get('error')): ?>
    <?php $type = 'danger'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if ($message = Flash::get('warning')): ?>
    <?php $type = 'warning'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if ($isAdminArea): ?>
    <div class="admin-layout">
      <?php require dirname(__DIR__) . '/partials/admin-sidebar.php'; ?>
      <section class="admin-content fade-in">
        <?php require $file; ?>
      </section>
    </div>
  <?php else: ?>
    <section class="fade-in">
      <?php require $file; ?>
    </section>
  <?php endif; ?>
</main>

<footer class="rd-footer py-5 mt-5">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
    <div>
      <strong class="d-block mb-1"><i class="fa-solid fa-heart me-2"></i>Rota do Amor</strong>
      <small>Uma plataforma de intenção relacional, confiança e sofisticação emocional para Moçambique.</small>
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
