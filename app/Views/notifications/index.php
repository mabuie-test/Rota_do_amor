<h3 class="mb-3"><i class="fa-regular fa-bell me-2"></i>Notificações</h3>
<?php if (empty($items ?? [])): ?>
<?php $title='Nenhuma notificação'; $description='Você verá aqui mensagens, matches e alertas importantes.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php else: ?>
<div class="d-flex flex-column gap-2">
<?php foreach (($items ?? []) as $item): ?>
  <div class="rd-list-item"><div class="d-flex justify-content-between"><strong><?= e($item['title']) ?></strong><small class="text-muted"><?= e($item['created_at']) ?></small></div><div class="small text-muted"><?= e($item['body']) ?></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
