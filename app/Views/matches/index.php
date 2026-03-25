<h2>Matches</h2>
<ul class="list-group">
<?php foreach (($matches ?? []) as $match): ?>
  <li class="list-group-item">Match #<?= (int) $match['id'] ?> (<?= e($match['status']) ?>)</li>
<?php endforeach; ?>
</ul>
