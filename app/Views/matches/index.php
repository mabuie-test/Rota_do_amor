<h3 class="mb-3"><i class="fa-solid fa-heart me-2 text-danger"></i>Seus Matches</h3>
<?php if (empty($matches ?? [])): ?>
<?php $title='Sem matches ainda'; $description='Continue curtindo perfis para criar conexões.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php else: ?>
<div class="row g-3">
<?php foreach (($matches ?? []) as $match): ?>
  <div class="col-md-6 col-lg-4"><div class="rd-card"><div class="card-body"><h6>Match #<?= (int) $match['id'] ?></h6><p class="small text-muted mb-2">Status: <?= e($match['status']) ?></p><a href="/messages" class="btn btn-sm btn-rd-primary">Conversar</a></div></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
