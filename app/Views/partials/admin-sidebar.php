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
      '/admin/super-dashboard' => ['fa-chart-pie', 'Executivo'],
      '/admin/admins' => ['fa-user-shield', 'Admins & Papéis'],
      '/admin/audit' => ['fa-clipboard-list', 'Auditoria'],
      '/admin/risk' => ['fa-triangle-exclamation', 'Risco & Abuso'],
      '/admin/safe-dates' => ['fa-shield-heart', 'Encontro Seguro'],
      '/admin/visitors' => ['fa-eye', 'Radar Visitantes'],
      '/admin/anonymous-stories' => ['fa-user-secret', 'Histórias Anónimas'],
      '/admin/compatibility-duels' => ['fa-people-arrows', 'Duelo Compatibilidade'],
      '/admin/settings' => ['fa-gear', 'Configurações'],
  ];
  foreach ($adminLinks as $href => [$icon, $label]):
      $isActive = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href);
  ?>
    <a href="<?= e(url($href)) ?>" class="<?= $isActive ? 'active' : '' ?>">
      <i class="fa-solid <?= e($icon) ?> me-2"></i><?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>
