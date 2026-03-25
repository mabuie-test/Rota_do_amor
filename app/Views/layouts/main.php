<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Rota do Amor') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<nav class="navbar navbar-expand-lg navbar-dark rd-navbar mb-4">
  <div class="container">
    <a class="navbar-brand" href="/"><i class="fa-solid fa-heart me-2"></i>Rota do Amor</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <div class="navbar-nav ms-auto gap-1">
        <a class="nav-link" href="/discover">Descobrir</a>
        <a class="nav-link" href="/swipe">Swipe</a>
        <a class="nav-link" href="/matches">Matches</a>
        <a class="nav-link" href="/messages">Mensagens</a>
        <a class="nav-link" href="/feed">Feed</a>
        <a class="nav-link" href="/notifications">Notificações</a>
        <a class="nav-link" href="/profile">Perfil</a>
      </div>
    </div>
  </div>
</nav>

<div class="container main-shell pb-5">
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

<footer class="rd-footer py-4 mt-4">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
    <div>
      <strong>Rota do Amor</strong><br>
      <small>Conexões verdadeiras em Moçambique.</small>
    </div>
    <div class="d-flex gap-3 align-items-center">
      <a href="/plans" class="text-decoration-none text-light">Planos</a>
      <a href="/about" class="text-decoration-none text-light">Sobre</a>
      <a href="/safety" class="text-decoration-none text-light">Segurança</a>
      <a href="#" class="text-decoration-none text-light"><i class="fa-brands fa-facebook"></i></a>
      <a href="#" class="text-decoration-none text-light"><i class="fa-brands fa-instagram"></i></a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
