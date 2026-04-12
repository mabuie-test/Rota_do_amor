<?php $admins = $admins ?? []; $roles = $roles ?? []; ?>
<h3 class="mb-3">Gestão de Admins & Papéis</h3>
<div class="rd-card mb-3"><div class="card-body">
<form method="post" action="/admin/admins/create" class="row g-2 align-items-end"><?= csrf_field() ?>
  <div class="col-md-3"><label class="form-label mb-1">Nome</label><input class="form-control form-control-sm" name="name" required></div>
  <div class="col-md-3"><label class="form-label mb-1">Email</label><input class="form-control form-control-sm" type="email" name="email" required></div>
  <div class="col-md-2"><label class="form-label mb-1">Password</label><input class="form-control form-control-sm" type="password" name="password" required></div>
  <div class="col-md-2"><label class="form-label mb-1">Papel</label><select class="form-select form-select-sm" name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><label class="form-label mb-1">Estado</label><select class="form-select form-select-sm" name="status"><option value="active">active</option><option value="inactive">inactive</option></select></div>
  <div class="col-auto"><button class="btn btn-sm btn-rd-primary">Criar admin</button></div>
</form>
</div></div>

<div class="rd-card"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
<thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Papel</th><th>Estado</th><th>Actualizar</th></tr></thead>
<tbody>
<?php foreach ($admins as $admin): ?>
<tr>
  <td><?= (int) $admin['id'] ?></td><td><?= e((string) $admin['name']) ?></td><td><?= e((string) $admin['email']) ?></td><td><?= e((string) $admin['role']) ?></td><td><?= e((string) $admin['status']) ?></td>
  <td>
    <form method="post" action="/admin/admins/<?= (int) $admin['id'] ?>/update" class="d-flex flex-wrap gap-1"><?= csrf_field() ?>
      <input type="hidden" name="name" value="<?= e((string) $admin['name']) ?>"><input type="hidden" name="email" value="<?= e((string) $admin['email']) ?>">
      <select name="role" class="form-select form-select-sm" style="width:160px"><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $role === $admin['role'] ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select>
      <select name="status" class="form-select form-select-sm" style="width:120px"><option value="active" <?= $admin['status'] === 'active' ? 'selected' : '' ?>>active</option><option value="inactive" <?= $admin['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option></select>
      <input name="password" placeholder="Nova password" class="form-control form-control-sm" style="width:180px">
      <button class="btn btn-sm btn-outline-primary">Salvar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
