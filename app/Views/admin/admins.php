<?php $admins = $admins ?? []; $roles = $roles ?? []; $matrix = $permission_matrix ?? []; $history = $history ?? []; ?>
<h3 class="mb-3">Gestão de Admins & Papéis</h3>
<div class="rd-card mb-3"><div class="card-body">
<form method="post" action="/admin/admins/create" class="row g-2 align-items-end"><?= csrf_field() ?>
  <div class="col-md-3"><label class="form-label mb-1">Nome</label><input class="form-control form-control-sm" name="name" required></div>
  <div class="col-md-3"><label class="form-label mb-1">Email</label><input class="form-control form-control-sm" type="email" name="email" required></div>
  <div class="col-md-2"><label class="form-label mb-1">Password</label><input class="form-control form-control-sm" type="password" name="password" required minlength="8"></div>
  <div class="col-md-2"><label class="form-label mb-1">Papel</label><select class="form-select form-select-sm" name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><label class="form-label mb-1">Estado</label><select class="form-select form-select-sm" name="status"><option value="active">active</option><option value="inactive">inactive</option></select></div>
  <div class="col-auto"><button class="btn btn-sm btn-rd-primary">Criar admin</button></div>
</form>
<p class="small text-muted mb-0 mt-2">Governança reforçada: validação de papel, proteção do último super admin e auditoria obrigatória de alterações.</p>
</div></div>

<div class="rd-card mb-3"><div class="card-body table-responsive">
  <h6 class="mb-2">Matriz de permissões por papel</h6>
  <table class="table table-modern align-middle mb-0"><thead><tr><th>Papel</th><th>Permissões</th></tr></thead><tbody>
      <?php foreach ($matrix as $role => $permissions): ?><tr><td><strong><?= e((string) $role) ?></strong></td><td><?php foreach ($permissions as $permission): ?><span class="rd-badge badge-active me-1"><?= e((string) $permission) ?></span><?php endforeach; ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div></div>

<div class="rd-card"><div class="card-body table-responsive">
<table class="table table-modern align-middle">
<thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Papel</th><th>Estado</th><th>Criado / Atualizado</th><th>Actualizar</th></tr></thead>
<tbody>
<?php foreach ($admins as $admin): ?>
<tr>
  <td><?= (int) $admin['id'] ?></td>
  <td><?= e((string) $admin['name']) ?></td>
  <td><?= e((string) $admin['email']) ?></td>
  <td><?= e((string) $admin['role']) ?></td>
  <td><?= e((string) $admin['status']) ?></td>
  <td><small>Criado por: <?= e((string) ($admin['created_by_name'] ?? 'n/d')) ?></small><br><small>Último update: <?= e((string) ($admin['last_updated_by_name'] ?? 'n/d')) ?> em <?= e((string) ($admin['updated_at'] ?? '—')) ?></small></td>
  <td>
    <form method="post" action="/admin/admins/<?= (int) $admin['id'] ?>/update" class="d-flex flex-wrap gap-1" onsubmit="return confirm('Confirmar atualização administrativa?');"><?= csrf_field() ?>
      <input name="name" value="<?= e((string) $admin['name']) ?>" class="form-control form-control-sm" style="width:130px">
      <input name="email" value="<?= e((string) $admin['email']) ?>" class="form-control form-control-sm" style="width:190px">
      <select name="role" class="form-select form-select-sm" style="width:170px"><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $role === $admin['role'] ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select>
      <select name="status" class="form-select form-select-sm" style="width:120px"><option value="active" <?= $admin['status'] === 'active' ? 'selected' : '' ?>>active</option><option value="inactive" <?= $admin['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option></select>
      <input name="password" placeholder="Nova password" class="form-control form-control-sm" style="width:170px" minlength="8">
      <?php if (($admin['role'] ?? '') === 'super_admin'): ?><input name="confirm_super_admin_change" placeholder="CONFIRMAR" class="form-control form-control-sm" style="width:130px"><?php endif; ?>
      <button class="btn btn-sm btn-outline-primary">Salvar</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<div class="rd-card mt-3"><div class="card-body table-responsive">
  <h6 class="mb-2">Histórico recente de alterações administrativas</h6>
  <table class="table table-sm align-middle mb-0"><thead><tr><th>Quando</th><th>Actor</th><th>Ação</th><th>Alvo</th><th>Detalhes</th></tr></thead><tbody>
  <?php foreach ($history as $item): ?>
    <tr><td><?= e((string) ($item['created_at'] ?? '')) ?></td><td><?= e((string) ($item['actor_name'] ?? 'system')) ?></td><td><?= e((string) ($item['action'] ?? '')) ?></td><td>admin#<?= (int) ($item['target_id'] ?? 0) ?></td><td><code><?= e((string) ($item['metadata_json'] ?? '{}')) ?></code></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div></div>
