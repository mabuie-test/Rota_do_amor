<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Rota do Amor') ?></title>
    <meta name="description" content="Rota do Amor: plataforma premium para conexões reais em Moçambique.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?php
use App\Core\Flash;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    '/discover' => ['label' => 'Descobrir', 'icon' => 'fa-compass'],
    '/swipe' => ['label' => 'Swipe', 'icon' => 'fa-bolt'],
    '/matches' => ['label' => 'Matches', 'icon' => 'fa-stars'],
    '/messages' => ['label' => 'Mensagens', 'icon' => 'fa-comments'],
    '/invites/received' => ['label' => 'Gostaram de Mim', 'icon' => 'fa-envelope-open-heart'],
    '/feed' => ['label' => 'Feed', 'icon' => 'fa-sparkles'],
    '/notifications' => ['label' => 'Notificações', 'icon' => 'fa-circle-check'],
    '/profile' => ['label' => 'Perfil', 'icon' => 'fa-heart-pulse'],
];
?>
<div class="rd-topbar py-2">
  <div class="container d-flex justify-content-between align-items-center small gap-2">
    <span><i class="fa-solid fa-crown me-2"></i>Rede premium para relações com intenção, ritmo e confiança.</span>
    <a href="<?= e(url('plans')) ?>" class="text-decoration-none text-white fw-semibold"><i class="fa-solid fa-stars me-1"></i>Ver planos</a>
  </div>
</div>

<nav class="navbar navbar-expand-lg navbar-dark rd-navbar sticky-top">
  <div class="container py-2">
    <a class="navbar-brand" href="<?= e(url()) ?>"><i class="fa-solid fa-heart me-2"></i>Rota do Amor</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <div class="navbar-nav ms-auto gap-1">
        <?php foreach ($navItems as $href => $item): ?>
          <?php $active = str_starts_with($path, $href) ? 'active' : ''; ?>
          <a class="nav-link <?= e($active) ?>" href="<?= e(url($href)) ?>"><i class="fa-solid <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</nav>

<div class="container main-shell py-4 py-md-5">
  <?php if ($message = Flash::get('success')): ?>
    <?php $type = 'success'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if ($message = Flash::get('error')): ?>
    <?php $type = 'danger'; require dirname(__DIR__) . '/partials/alert.php'; ?>
  <?php endif; ?>
  <?php if (str_starts_with($path, '/admin') && $path !== '/admin/login'): ?>
    <div class="admin-layout">
      <?php require dirname(__DIR__) . '/partials/admin-sidebar.php'; ?>
      <div class="admin-content">
        <?php require $file; ?>
      </div>
    </div>
  <?php else: ?>
    <?php require $file; ?>
  <?php endif; ?>
</div>

<footer class="rd-footer py-5 mt-5">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
    <div>
      <strong class="d-block mb-1"><i class="fa-solid fa-heart me-2"></i>Rota do Amor</strong>
      <small>Conexões verdadeiras em Moçambique, com sofisticação, segurança e presença humana.</small>
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
