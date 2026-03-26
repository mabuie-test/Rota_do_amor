<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Descobrir Pessoas</h3>
  <button class="btn btn-rd-soft"><i class="fa-solid fa-sliders me-2"></i>Filtros</button>
</div>
<div class="row g-3">
<?php if (empty($profiles ?? [])): ?>
  <div class="col-12"><?php $title='Sem sugestões por agora'; $description='Volte em alguns instantes para novos perfis.'; require dirname(__DIR__).'/partials/empty-state.php'; ?></div>
<?php endif; ?>
<?php foreach (($profiles ?? []) as $profile): ?>
  <div class="col-md-6 col-xl-4">
    <div class="rd-card h-100">
      <div class="card-body">
        <h5><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
        <p class="small text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= e((string) ($profile['city_id'] ?? '')) ?></p>
        <p class="small"><?= e($profile['relationship_goal'] ?? '') ?></p>
        <div class="d-flex justify-content-between align-items-center">
          <span class="rd-badge badge-active">Compatibilidade <?= (int) (($profile['_compatibility'] ?? 0)) ?>%</span>
          <div class="d-flex gap-1"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-heart"></i></button><button class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-star"></i></button></div>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
