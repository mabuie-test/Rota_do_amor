<?php
/** @var array<string, array{label:string,icon:string,group:string}> $appNavItems */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$groups = [];
foreach ($appNavItems as $href => $item) {
    $groups[$item['group']][$href] = $item;
}
$sidebarMode = $sidebarMode ?? 'desktop';
?>
<aside class="rd-sidebar rd-sidebar--<?= e($sidebarMode) ?>" aria-label="Navegação principal da aplicação">
  <div class="rd-sidebar__section rd-sidebar__intro">
    <h6 class="text-white mb-1"><i class="fa-solid fa-heart me-2"></i>Produto</h6>
    <p class="small text-white-50 mb-0">Conexões, mensagens e segurança relacional</p>
  </div>

  <?php foreach ($groups as $groupLabel => $links): ?>
    <div class="rd-sidebar__section">
      <p class="rd-sidebar__title"><?= e($groupLabel) ?></p>
      <?php foreach ($links as $href => $item): ?>
        <?php $active = $href === '/dashboard' ? $path === $href : str_starts_with($path, $href); ?>
        <a href="<?= e(url($href)) ?>" class="rd-sidebar__link <?= $active ? 'is-active' : '' ?>">
          <i class="fa-solid <?= e($item['icon']) ?> rd-icon-md"></i>
          <span><?= e($item['label']) ?></span>
          <?php if ($href === '/notifications' && ($layout_unread_notifications ?? 0) > 0): ?>
            <span class="badge text-bg-danger"><?= (int) $layout_unread_notifications > 99 ? '99+' : (int) $layout_unread_notifications ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</aside>
