<?php $unreadCount = (int) ($unread_count ?? 0); ?>
<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-regular fa-bell"></i></span>Notificações</h3>
    <p class="rd-page-header__subtitle">Eventos operacionais e relacionais do teu percurso na plataforma.</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if ($unreadCount > 0): ?><span class="rd-badge badge-failed"><i class="fa-solid fa-bolt"></i><?= $unreadCount ?> não lidas</span><?php endif; ?>
    <form method="post" action="/notifications/read-all" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-check-double me-1"></i>Marcar todas como lidas</button></form>
  </div>
</div>
<?php if (empty($items ?? [])): ?>
<?php $title='Nenhuma notificação'; $description='Você verá aqui mensagens, matches e alertas importantes.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php else: ?>
<div class="d-flex flex-column gap-2">
<?php foreach (($items ?? []) as $item): ?>
  <?php $isRead = (int) ($item['is_read'] ?? 0) === 1; ?>
  <a href="/notifications/<?= (int) ($item['id'] ?? 0) ?>/go" class="rd-list-item rd-notification-item text-decoration-none d-block <?= $isRead ? 'is-read' : 'is-unread' ?>">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div>
        <strong class="d-block"><i class="fa-solid fa-circle-dot me-1"></i><?= e((string) ($item['title'] ?? 'Notificação')) ?></strong>
        <div class="rd-supporting-text"><?= e((string) ($item['body'] ?? '')) ?></div>
        <div class="small mt-1 rd-notification-destination">
          <i class="fa-solid fa-location-arrow me-1"></i><?= $isRead ? 'Abrir novamente' : 'Abrir contexto' ?>
        </div>
      </div>
      <div class="text-end">
        <small class="rd-meta-text text-nowrap d-block"><?= e((string) ($item['created_at'] ?? '')) ?></small>
        <span class="badge <?= $isRead ? 'text-bg-light' : 'text-bg-danger' ?> mt-1"><?= $isRead ? 'Lida' : 'Nova' ?></span>
      </div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
