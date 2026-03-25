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
<nav class="navbar navbar-expand-lg navbar-dark bg-black mb-4">
  <div class="container">
    <a class="navbar-brand" href="/">Rota do Amor</a>
    <div class="navbar-nav ms-auto gap-2">
      <a class="nav-link" href="/discover">Descobrir</a>
      <a class="nav-link" href="/swipe">Swipe</a>
      <a class="nav-link" href="/matches">Matches</a>
      <a class="nav-link" href="/feed">Feed</a>
      <a class="nav-link" href="/profile">Perfil</a>
    </div>
  </div>
</nav>
<div class="container py-2">
    <?php require $file; ?>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
