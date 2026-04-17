<div class="rd-page-header text-center justify-content-center">
  <div>
    <h3 class="rd-page-header__title justify-content-center"><span class="rd-page-header__icon"><i class="fa-solid fa-hand-sparkles"></i></span>Swipe Premium</h3>
    <p class="rd-page-header__subtitle">Decisão rápida com leitura contextual e ação imediata.</p>
  </div>
</div>
<?php if (!empty($candidate)): ?>
<div class="swipe-card rd-card mb-3">
  <div class="card-body text-center p-4">
    <h4 class="mb-1"><?= e(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? '')) ?></h4>
    <p class="rd-meta-text mb-2"><i class="fa-solid fa-location-dot me-1"></i><?= e((string) ($candidate['city_id'] ?? '')) ?></p>
    <p class="rd-supporting-text"><?= e($candidate['bio'] ?? '') ?></p>
    <span class="rd-badge badge-active"><i class="fa-solid fa-chart-line"></i>Compatibilidade alta</span>
  </div>
</div>
<div class="swipe-cta d-flex justify-content-center gap-3">
  <button data-swipe-action class="btn btn-light border" title="Passar"><i class="fa-solid fa-xmark text-danger"></i></button>
  <button data-swipe-action class="btn btn-rd-primary" title="Gostar"><i class="fa-solid fa-heart"></i></button>
  <button data-swipe-action class="btn btn-light border" title="Super destaque"><i class="fa-solid fa-star text-warning"></i></button>
</div>
<?php else: ?>
<?php $title='Nenhum perfil disponível'; $description='Aguarde novos membros para continuar o swipe.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php endif; ?>
