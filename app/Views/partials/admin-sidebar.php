<?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<div class="admin-sidebar">
  <h6 class="text-white mb-3"><i class="fa-solid fa-crown me-2"></i>Admin</h6>
  <?php
  $adminLinks = [
      '/admin' => ['fa-chart-line', 'Dashboard'],
      '/admin/users' => ['fa-users', 'Utilizadores'],
      '/admin/payments' => ['fa-money-bill-wave', 'Pagamentos'],
      '/admin/subscriptions' => ['fa-calendar-check', 'Subscrições'],
      '/admin/boosts' => ['fa-bolt', 'Boosts'],
      '/admin/verifications' => ['fa-id-card', 'Verificações'],
      '/admin/reports' => ['fa-flag', 'Denúncias'],
      '/admin/moderation' => ['fa-gavel', 'Moderação'],
      '/admin/settings' => ['fa-gear', 'Configurações'],
  ];
  foreach ($adminLinks as $href => [$icon, $label]):
      $isActive = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href);
  ?>
    <a href="<?= e($href) ?>" class="<?= $isActive ? 'active' : '' ?>">
      <i class="fa-solid <?= e($icon) ?> me-2"></i><?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>
