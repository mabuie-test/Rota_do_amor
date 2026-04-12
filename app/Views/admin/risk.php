<?php $users = $users ?? []; ?>
<h3 class="mb-3">Centro de Risco & Abuso</h3>
<div class="rd-card"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
<thead><tr><th>Utilizador</th><th>Status</th><th>Denúncias</th><th>Bloqueios</th><th>Msgs 24h</th><th>Acção</th></tr></thead>
<tbody>
<?php foreach ($users as $user): ?>
  <tr>
    <td>#<?= (int) $user['id'] ?> · <?= e((string) $user['first_name']) ?> <?= e((string) $user['last_name']) ?><br><small><?= e((string) $user['email']) ?></small></td>
    <td><?= e((string) $user['status']) ?></td>
    <td><?= (int) $user['reports_count'] ?></td>
    <td><?= (int) $user['blocked_count'] ?></td>
    <td><?= (int) $user['messages_24h'] ?></td>
    <td><a class="btn btn-sm btn-outline-dark" href="/admin/users/<?= (int) $user['id'] ?>">Abrir detalhe</a></td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
