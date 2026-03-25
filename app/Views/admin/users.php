<h3 class="mb-3">Utilizadores</h3>
<div class="rd-card"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
  <thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Status</th><th>Premium</th><th>Criado</th></tr></thead>
  <tbody>
    <?php foreach (($users ?? []) as $u): ?>
      <tr><td><?= (int) $u['id'] ?></td><td><?= e($u['first_name'].' '.$u['last_name']) ?></td><td><?= e($u['email']) ?></td><td><?php $kind=$u['status']==='active'?'active':'pending'; $label=$u['status']; require dirname(__DIR__).'/partials/badge.php'; ?></td><td><?= e($u['premium_status']) ?></td><td><?= e($u['created_at']) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
