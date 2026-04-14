<h3 class="mb-3"><i class="fa-regular fa-bell me-2"></i>Notificações</h3>
<?php if (empty($items ?? [])): ?>
<?php $title='Nenhuma notificação'; $description='Você verá aqui mensagens, matches e alertas importantes.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php else: ?>
<div class="d-flex flex-column gap-2">
<?php foreach (($items ?? []) as $item): ?>
  <?php $isRead = (int) ($item['is_read'] ?? 0) === 1; ?>
  <a href="/notifications/<?= (int) ($item['id'] ?? 0) ?>/go" class="rd-list-item rd-notification-item text-decoration-none d-block <?= $isRead ? 'is-read' : 'is-unread' ?>">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div>
        <strong class="d-block"><?= e((string) ($item['title'] ?? 'Notificação')) ?></strong>
        <div class="small text-muted"><?= e((string) ($item['body'] ?? '')) ?></div>
        <div class="small mt-1 rd-notification-destination">
          <i class="fa-solid fa-location-arrow me-1"></i>Ir para destino contextual
        </div>
      </div>
      <small class="text-muted text-nowrap"><?= e((string) ($item['created_at'] ?? '')) ?></small>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
