<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h3 class="mb-1"><i class="fa-solid fa-envelope-open-heart me-2"></i>Quem Gostou de Mim</h3>
    <p class="text-muted mb-0">Interesse qualificado com intenção, ritmo e compatibilidade do momento.</p>
  </div>
  <a class="btn btn-sm btn-rd-soft" href="/invites/sent"><i class="fa-solid fa-paper-plane me-1"></i>Ver enviados</a>
</div>

<form method="get" class="d-flex flex-wrap gap-2 mb-3">
  <select name="status" class="form-select form-select-sm" style="width:190px">
    <option value="">Todos os estados</option>
    <?php foreach (['pending' => 'Pendentes', 'accepted' => 'Aceites', 'declined' => 'Recusados', 'expired' => 'Expirados'] as $value => $label): ?>
      <option value="<?= e($value) ?>" <?= (($filters['status'] ?? '') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="invitation_type" class="form-select form-select-sm" style="width:220px">
    <option value="">Todos os tipos</option>
    <option value="standard" <?= (($filters['invitation_type'] ?? '') === 'standard') ? 'selected' : '' ?>>Convite standard</option>
    <option value="priority" <?= (($filters['invitation_type'] ?? '') === 'priority') ? 'selected' : '' ?>>Convite prioritário</option>
  </select>
  <button class="btn btn-sm btn-rd-soft"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
</form>

<div class="row g-3">
  <?php if (empty($invites)): ?>
    <div class="col-12"><?php $title='Sem convites por enquanto'; $description='Quando alguém demonstrar interesse qualificado, você verá aqui.'; require dirname(__DIR__) . '/partials/empty-state.php'; ?></div>
  <?php endif; ?>

  <?php foreach ($invites as $invite): ?>
    <div class="col-lg-6">
      <div class="rd-card h-100 rd-invite-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <div class="fw-semibold fs-5"><?= e((string) ($invite['sender_name'] ?? 'Perfil')) ?></div>
              <div class="small text-muted">Compatibilidade <strong><?= (float) ($invite['compatibility_score_snapshot'] ?? 0) ?>%</strong></div>
            </div>
            <?php if (!empty($invite['is_priority'])): ?><span class="rd-badge badge-premium"><i class="fa-solid fa-crown"></i>Prioritário</span><?php endif; ?>
          </div>

          <div class="rd-heart-mode-card mb-2">
            <div class="d-flex flex-wrap gap-2 mb-2">
              <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($invite['intention_icon'] ?? 'fa-heart-pulse')) ?>"></i><?= e((string) ($invite['intention_label'] ?? '')) ?></span>
              <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($invite['pace_icon'] ?? 'fa-wave-square')) ?>"></i><?= e((string) ($invite['pace_label'] ?? '')) ?></span>
            </div>
            <?php if (!empty($invite['opening_message'])): ?><p class="small mb-0">“<?= e((string) $invite['opening_message']) ?>”</p><?php endif; ?>
          </div>

          <div class="small text-muted mb-2">Estado: <strong><?= e((string) ($invite['status'] ?? 'pending')) ?></strong> · recebido em <?= e((string) ($invite['created_at'] ?? '')) ?></div>

          <?php if (!empty($invite['is_pending'])): ?>
          <div class="d-flex gap-2">
            <form method="post" action="/invites/accept"><?= csrf_field() ?>
              <input type="hidden" name="invite_id" value="<?= (int) $invite['id'] ?>">
              <button class="btn btn-sm btn-rd-primary"><i class="fa-solid fa-check me-1"></i>Aceitar</button>
            </form>
            <form method="post" action="/invites/decline"><?= csrf_field() ?>
              <input type="hidden" name="invite_id" value="<?= (int) $invite['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-xmark me-1"></i>Recusar</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
