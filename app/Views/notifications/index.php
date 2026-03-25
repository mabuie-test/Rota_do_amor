<h2>Notificações</h2>
<ul class="list-group">
<?php foreach (($items ?? []) as $item): ?>
  <li class="list-group-item"><?= e($item['title']) ?> - <?= e($item['body']) ?></li>
<?php endforeach; ?>
</ul>
