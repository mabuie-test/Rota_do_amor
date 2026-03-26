<h3 class="mb-3">Meu Perfil</h3>
<?php if (!empty($profile)): ?>
<div class="rd-card rd-profile-card">
  <div class="card-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div class="avatar"><?= e(strtoupper(substr((string) ($profile['first_name'] ?? 'U'),0,1))) ?></div>
      <div>
        <h5 class="mb-1"><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
        <p class="text-muted mb-1"><i class="fa-solid fa-location-dot me-1"></i><?= e(($profile['city_name'] ?? '') . ', ' . ($profile['province_name'] ?? '')) ?></p>
        <?php foreach (($badges ?? []) as $badge): $kind = $badge['badge_type']; $label = ucfirst($badge['badge_type']); require dirname(__DIR__).'/partials/badge.php'; endforeach; ?>
      </div>
    </div>
    <p class="mb-0"><?= e($profile['bio'] ?? 'Adicione uma bio para melhorar seu perfil.') ?></p>
  </div>
</div>
<?php else: ?>
<?php $title='Perfil não encontrado'; $description='Complete seu registo e volte novamente.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php endif; ?>
