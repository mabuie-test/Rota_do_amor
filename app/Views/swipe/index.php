<h3 class="mb-3 text-center">Swipe Premium</h3>
<?php if (!empty($candidate)): ?>
<div class="swipe-card rd-card mb-3">
  <div class="card-body text-center p-4">
    <h4><?= e(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? '')) ?></h4>
    <p class="text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= e((string) ($candidate['city_id'] ?? '')) ?></p>
    <p><?= e($candidate['bio'] ?? '') ?></p>
    <span class="rd-badge badge-active">Compatibilidade alta</span>
  </div>
</div>
<div class="swipe-cta d-flex justify-content-center gap-3">
  <button data-swipe-action class="btn btn-light border"><i class="fa-solid fa-xmark text-danger"></i></button>
  <button data-swipe-action class="btn btn-rd-primary"><i class="fa-solid fa-heart"></i></button>
  <button data-swipe-action class="btn btn-light border"><i class="fa-solid fa-star text-warning"></i></button>
</div>
<?php else: ?>
<?php $title='Nenhum perfil disponível'; $description='Aguarde novos membros para continuar o swipe.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php endif; ?>
