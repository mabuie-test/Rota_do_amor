<h2>Feed Social</h2>
<?php foreach (($feed ?? []) as $post): ?>
  <div class="card mb-2"><div class="card-body"><?= e($post['content']) ?></div></div>
<?php endforeach; ?>
