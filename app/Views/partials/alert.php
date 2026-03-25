<?php if (!empty($message ?? null)): ?>
<div class="alert alert-<?= e($type ?? 'info') ?> d-flex justify-content-between align-items-center fade-in">
  <span><?= e($message) ?></span>
  <button class="btn btn-sm btn-outline-secondary" type="button" data-dismiss-alert><i class="fa-solid fa-xmark"></i></button>
</div>
<?php endif; ?>
