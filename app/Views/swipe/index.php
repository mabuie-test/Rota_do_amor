<h2>Swipe</h2>
<?php if (!empty($candidate)): ?>
<div class="card">
  <div class="card-body">
    <h5><?= e(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? '')) ?></h5>
    <p><?= e($candidate['bio'] ?? '') ?></p>
    <button data-swipe-action class="btn btn-success">Like</button>
    <button data-swipe-action class="btn btn-secondary">Pass</button>
  </div>
</div>
<?php else: ?>
<p>Sem candidatos no momento.</p>
<?php endif; ?>
