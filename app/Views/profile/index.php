<h2 class="mb-3">Meu Perfil</h2>
<?php if (!empty($profile)): ?>
<div class="card bg-dark text-light">
  <div class="card-body">
    <h5><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
    <p><?= e($profile['bio'] ?? 'Sem bio') ?></p>
    <small><?= e(($profile['city_name'] ?? '') . ', ' . ($profile['province_name'] ?? '')) ?></small>
  </div>
</div>
<?php else: ?>
<p>Perfil não encontrado.</p>
<?php endif; ?>
