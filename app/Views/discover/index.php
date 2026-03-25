<h2>Descobrir Perfis</h2>
<div class="row">
<?php foreach (($profiles ?? []) as $profile): ?>
  <div class="col-md-4 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <h5><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
        <p><?= e($profile['relationship_goal'] ?? '') ?></p>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
