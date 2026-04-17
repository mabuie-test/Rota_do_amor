<?php if (!empty($message ?? null)): ?>
<div class="alert alert-<?= e($type ?? 'info') ?> rd-alert d-flex justify-content-between align-items-center fade-in" role="alert">
  <span><i class="fa-solid fa-circle-info me-1"></i><?= e($message) ?></span>
  <button class="btn btn-sm btn-outline-secondary" type="button" data-dismiss-alert aria-label="Fechar alerta"><i class="fa-solid fa-xmark"></i></button>
</div>
<?php endif; ?>
