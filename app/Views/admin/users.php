<h3 class="mb-3">Utilizadores</h3>

<div class="rd-card mb-3">
  <div class="card-body">
    <form method="get" action="/admin/users" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Filtrar por status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Todos</option>
          <?php foreach (($allowedStatuses ?? []) as $statusOption): ?>
            <option value="<?= e($statusOption) ?>" <?= ($currentStatusFilter ?? '') === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-rd-primary">Aplicar filtro</button>
      </div>
      <div class="col-auto">
        <a href="/admin/users" class="btn btn-sm btn-outline-secondary">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="rd-card">
  <div class="card-body table-responsive">
    <table class="table table-modern align-middle">
      <thead>
      <tr>
        <th>ID</th>
        <th>Nome</th>
        <th>Email</th>
        <th>Status</th>
        <th>Premium</th>
        <th>Email verificado</th>
        <th>Criado em</th>
        <th>Acções</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach (($users ?? []) as $u): ?>
          <?php
          $status = (string) ($u['status'] ?? 'pending_activation');
          $kind = match ($status) {
              'active' => 'active',
              'pending_activation', 'pending_verification' => 'pending',
              'suspended', 'expired' => 'failed',
              'banned' => 'failed',
              default => 'pending',
          };
          ?>
        <tr>
          <td><?= (int) $u['id'] ?></td>
          <td><a href="/admin/users/<?= (int) $u['id'] ?>" class="text-decoration-none fw-semibold"><?= e(trim((string) (($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))) ?></a></td>
          <td><?= e((string) ($u['email'] ?? '')) ?></td>
          <td><?php $label = $status; require dirname(__DIR__) . '/partials/badge.php'; ?></td>
          <td><?= e((string) ($u['premium_status'] ?? 'basic')) ?></td>
          <td><?= !empty($u['email_verified_at']) ? '<span class="text-success">Sim</span>' : '<span class="text-muted">Não</span>' ?></td>
          <td><?= e((string) ($u['created_at'] ?? '')) ?></td>
          <td style="min-width:280px;">
            <div class="d-flex flex-wrap gap-1 mb-2">
              <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="active"><input type="hidden" name="reason" value="Ação administrativa: activar conta"><button class="btn btn-sm btn-outline-success">Activar</button></form>
              <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="expired"><input type="hidden" name="reason" value="Ação administrativa: desactivar conta"><button class="btn btn-sm btn-outline-secondary">Desactivar</button></form>
              <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="suspended"><input type="hidden" name="reason" value="Ação administrativa: suspender conta"><button class="btn btn-sm btn-outline-warning">Suspender</button></form>
              <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="banned"><input type="hidden" name="reason" value="Ação administrativa: banir conta"><button class="btn btn-sm btn-outline-danger">Banir</button></form>
            </div>
            <div class="d-flex flex-wrap gap-1">
              <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/resend-verification-email"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Reenviar verificação</button></form>
              <a href="/admin/users/<?= (int) $u['id'] ?>" class="btn btn-sm btn-dark">Ver detalhe</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
