<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h3 class="mb-1"><i class="fa-solid fa-paper-plane me-2"></i>Convites Enviados</h3>
    <p class="text-muted mb-0">Acompanhe status, prioridade e alinhamento dos convites que você enviou.</p>
  </div>
  <a class="btn btn-sm btn-rd-soft" href="/invites/received"><i class="fa-solid fa-heart me-1"></i>Quem gostou de mim</a>
</div>

<form method="get" class="d-flex flex-wrap gap-2 mb-3">
  <select name="status" class="form-select form-select-sm" style="width:220px">
    <option value="">Todos os estados</option>
    <?php foreach (['pending' => 'Pendentes', 'accepted' => 'Aceites', 'declined' => 'Recusados', 'expired' => 'Expirados', 'cancelled' => 'Cancelados'] as $value => $label): ?>
      <option value="<?= e($value) ?>" <?= (($filters['status'] ?? '') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-sm btn-rd-soft"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
</form>

<div class="row g-3">
  <?php if (empty($invites)): ?>
    <div class="col-12"><?php $title='Nenhum convite enviado'; $description='Use a descoberta para enviar convites com intenção.'; require dirname(__DIR__) . '/partials/empty-state.php'; ?></div>
  <?php endif; ?>

  <?php foreach ($invites as $invite): ?>
    <div class="col-lg-6">
      <div class="rd-card h-100 rd-invite-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <div class="fw-semibold fs-5"><?= e((string) ($invite['receiver_name'] ?? 'Perfil')) ?></div>
              <div class="small text-muted">Compatibilidade no envio: <strong><?= (float) ($invite['compatibility_score_snapshot'] ?? 0) ?>%</strong></div>
            </div>
            <div class="d-flex gap-1">
              <?php if (!empty($invite['is_priority'])): ?><span class="rd-badge badge-premium"><i class="fa-solid fa-crown"></i>Prioritário</span><?php endif; ?>
              <span class="rd-badge badge-active"><?= e((string) ($invite['status'] ?? 'pending')) ?></span>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($invite['intention_icon'] ?? 'fa-heart-pulse')) ?>"></i><?= e((string) ($invite['intention_label'] ?? '')) ?></span>
            <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($invite['pace_icon'] ?? 'fa-wave-square')) ?>"></i><?= e((string) ($invite['pace_label'] ?? '')) ?></span>
          </div>

          <?php if (!empty($invite['opening_message'])): ?><p class="small mb-2">Mensagem: “<?= e((string) $invite['opening_message']) ?>”</p><?php endif; ?>
          <div class="small text-muted">Enviado em <?= e((string) ($invite['created_at'] ?? '')) ?><?php if (!empty($invite['responded_at'])): ?> · respondido em <?= e((string) $invite['responded_at']) ?><?php endif; ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
