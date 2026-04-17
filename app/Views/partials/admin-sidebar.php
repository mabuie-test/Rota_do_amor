<?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<aside class="rd-sidebar" aria-label="Navegação administrativa">
  <div class="rd-sidebar__section">
    <h6 class="text-white mb-1"><i class="fa-solid fa-shield-halved me-2"></i>Admin Console</h6>
    <p class="small text-white-50 mb-0">Operações, risco, auditoria e governança</p>
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
      'Segurança' => [
          '/admin/risk' => ['fa-triangle-exclamation', 'Risco & Abuso'],
          '/admin/reports' => ['fa-flag', 'Denúncias'],
          '/admin/moderation' => ['fa-gavel', 'Moderação'],
          '/admin/verifications' => ['fa-id-card', 'Verificações'],
          '/admin/audit' => ['fa-clipboard-list', 'Auditoria'],
      ],
      'Módulos' => [
          '/admin/safe-dates' => ['fa-shield-heart', 'Encontro Seguro'],
          '/admin/visitors' => ['fa-eye', 'Radar Visitantes'],
          '/admin/anonymous-stories' => ['fa-user-secret', 'Histórias Anónimas'],
          '/admin/compatibility-duels' => ['fa-people-arrows', 'Duelo Compatibilidade'],
          '/admin/settings' => ['fa-gear', 'Configurações'],
      ],
  ];
  ?>

  <?php foreach ($adminSections as $sectionLabel => $links): ?>
    <div class="rd-sidebar__section">
      <p class="rd-sidebar__title"><?= e($sectionLabel) ?></p>
      <?php foreach ($links as $href => [$icon, $label]): ?>
        <?php $isActive = $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href); ?>
        <a href="<?= e(url($href)) ?>" class="rd-sidebar__link <?= $isActive ? 'is-active' : '' ?>">
          <i class="fa-solid <?= e($icon) ?> rd-icon-md"></i><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</aside>
