<h3 class="mb-3">Moderação</h3>

<div class="rd-card mb-3">
  <div class="card-body">
    <h6 class="mb-3">Executar acção</h6>
    <form method="post" action="/admin/moderation/suspend" id="moderation-form" class="row g-2">
      <?= csrf_field() ?>
      <div class="col-md-2">
        <label class="form-label mb-1">ID Utilizador</label>
        <input type="number" name="user_id" class="form-control form-control-sm" min="1" required>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Acção</label>
        <select class="form-select form-select-sm" name="action_route" id="action-route">
          <?php foreach (($allowedActions ?? []) as $action): ?>
            <option value="<?= e($action) ?>"><?= e($action) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label mb-1">Motivo</label>
        <input name="reason" class="form-control form-control-sm" placeholder="Motivo da acção" required>
      </div>
      <div class="col-md-2 d-grid">
        <label class="form-label mb-1">&nbsp;</label>
        <button class="btn btn-sm btn-rd-primary">Executar</button>
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
        <th>Admin</th>
        <th>Utilizador</th>
        <th>Ação</th>
        <th>Motivo</th>
        <th>Data</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach (($actions ?? []) as $a): ?>
        <tr>
          <td><?= (int) $a['id'] ?></td>
          <td>#<?= (int) $a['admin_id'] ?> · <?= e((string) ($a['admin_name'] ?? '')) ?></td>
          <td>
            <a href="/admin/users/<?= (int) $a['user_id'] ?>" class="text-decoration-none">#<?= (int) $a['user_id'] ?></a>
            <div class="small text-muted"><?= e((string) ($a['user_name'] ?? '')) ?> · <?= e((string) ($a['user_email'] ?? '')) ?></div>
          </td>
          <td><?= e((string) $a['action_type']) ?></td>
          <td><?= e((string) $a['reason']) ?></td>
          <td><?= e((string) $a['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  (() => {
    const form = document.getElementById('moderation-form');
    const select = document.getElementById('action-route');
    if (!form || !select) return;

    const syncAction = () => {
      form.action = '/admin/moderation/' + select.value;
    };

    select.addEventListener('change', syncAction);
    syncAction();
  })();
</script>
