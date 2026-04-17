<?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<aside class="admin-sidebar">
  <div class="admin-sidebar__header">
    <h6 class="text-white mb-1"><i class="fa-solid fa-crown me-2"></i>Admin Console</h6>
    <small class="text-white-50">Operações, risco e governança</small>
  </div>

  <?php
  $adminSections = [
      'Core' => [
          '/admin' => ['fa-chart-line', 'Dashboard'],
          '/admin/super-dashboard' => ['fa-chart-pie', 'Executivo'],
          '/admin/users' => ['fa-users', 'Utilizadores'],
          '/admin/admins' => ['fa-user-shield', 'Admins & Papéis'],
      ],
      'Financeiro' => [
          '/admin/payments' => ['fa-money-bill-wave', 'Pagamentos'],
          '/admin/subscriptions' => ['fa-calendar-check', 'Subscrições'],
          '/admin/boosts' => ['fa-bolt', 'Boosts'],
      ],
      'Segurança e Conteúdo' => [
          '/admin/risk' => ['fa-triangle-exclamation', 'Risco & Abuso'],
          '/admin/reports' => ['fa-flag', 'Denúncias'],
          '/admin/moderation' => ['fa-gavel', 'Moderação'],
          '/admin/verifications' => ['fa-id-card', 'Verificações'],
          '/admin/audit' => ['fa-clipboard-list', 'Auditoria'],
      ],
      'Módulos de Produto' => [
          '/admin/safe-dates' => ['fa-shield-heart', 'Encontro Seguro'],
          '/admin/visitors' => ['fa-eye', 'Radar Visitantes'],
          '/admin/anonymous-stories' => ['fa-user-secret', 'Histórias Anónimas'],
          '/admin/compatibility-duels' => ['fa-people-arrows', 'Duelo Compatibilidade'],
          '/admin/settings' => ['fa-gear', 'Configurações'],
      ],
  ];
  ?>

  <?php foreach ($adminSections as $sectionLabel => $links): ?>
    <div class="admin-sidebar__group">
      <span class="admin-sidebar__group-title"><?= e($sectionLabel) ?></span>
      <?php foreach ($links as $href => [$icon, $label]): ?>
        <?php $isActive = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
        <a href="<?= e(url($href)) ?>" class="<?= $isActive ? 'active' : '' ?>">
          <i class="fa-solid <?= e($icon) ?> me-2"></i><?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</aside>
