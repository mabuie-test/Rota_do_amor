<?php
$user = $user ?? [];
$latestVerification = $latestVerification ?? null;
$moderationActions = $moderationActions ?? [];
$photos = $photos ?? [];
?>

<h3 class="mb-3">Detalhe do Utilizador #<?= (int) ($user['id'] ?? 0) ?></h3>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="rd-card h-100">
      <div class="card-body">
        <h6>Dados principais</h6>
        <ul class="list-unstyled small mb-0">
          <li><strong>Nome:</strong> <?= e(trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))) ?></li>
          <li><strong>Email:</strong> <?= e((string) ($user['email'] ?? '')) ?></li>
          <li><strong>Status:</strong> <?= e((string) ($user['status'] ?? '')) ?></li>
          <li><strong>Premium:</strong> <?= e((string) ($user['premium_status'] ?? '')) ?></li>
          <li><strong>Email verificado em:</strong> <?= e((string) ($user['email_verified_at'] ?? 'Não verificado')) ?></li>
          <li><strong>Criado em:</strong> <?= e((string) ($user['created_at'] ?? '')) ?></li>
          <li><strong>Actualizado em:</strong> <?= e((string) ($user['updated_at'] ?? '')) ?></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="rd-card h-100">
      <div class="card-body">
        <h6>Verificação de identidade (mais recente)</h6>
        <?php if (!empty($latestVerification)): ?>
          <ul class="list-unstyled small mb-2">
            <li><strong>Status:</strong> <?= e((string) ($latestVerification['status'] ?? '')) ?></li>
            <li><strong>Motivo de rejeição:</strong> <?= e((string) ($latestVerification['rejection_reason'] ?? '—')) ?></li>
            <li><strong>Revisado por admin:</strong> <?= e((string) ($latestVerification['reviewed_by_admin_name'] ?? ('#' . (int) ($latestVerification['reviewed_by_admin_id'] ?? 0)))) ?></li>
            <li><strong>Actualizado em:</strong> <?= e((string) ($latestVerification['updated_at'] ?? '')) ?></li>
          </ul>
          <div class="d-flex gap-2 small">
            <a href="<?= e('/' . ltrim((string) ($latestVerification['document_image_path'] ?? ''), '/')) ?>" target="_blank" rel="noopener">Documento</a>
            <a href="<?= e('/' . ltrim((string) ($latestVerification['selfie_image_path'] ?? ''), '/')) ?>" target="_blank" rel="noopener">Selfie</a>
          </div>
        <?php else: ?>
          <p class="small text-muted mb-0">Sem envio de verificação.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="rd-card">
      <div class="card-body">
        <h6>Acções administrativas</h6>
        <div class="d-flex flex-wrap gap-2 mb-2">
          <form method="post" action="/admin/users/<?= (int) ($user['id'] ?? 0) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="active"><input type="hidden" name="reason" value="Ação administrativa: activar conta"><button class="btn btn-sm btn-outline-success">Activar</button></form>
          <form method="post" action="/admin/users/<?= (int) ($user['id'] ?? 0) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="expired"><input type="hidden" name="reason" value="Ação administrativa: desactivar conta"><button class="btn btn-sm btn-outline-secondary">Desactivar</button></form>
          <form method="post" action="/admin/users/<?= (int) ($user['id'] ?? 0) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="suspended"><input type="hidden" name="reason" value="Ação administrativa: suspender conta"><button class="btn btn-sm btn-outline-warning">Suspender</button></form>
          <form method="post" action="/admin/users/<?= (int) ($user['id'] ?? 0) ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="banned"><input type="hidden" name="reason" value="Ação administrativa: banir conta"><button class="btn btn-sm btn-outline-danger">Banir</button></form>
          <form method="post" action="/admin/users/<?= (int) ($user['id'] ?? 0) ?>/resend-verification-email"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Reenviar verificação</button></form>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="rd-card h-100">
      <div class="card-body">
        <h6>Fotos do perfil</h6>
        <?php if (empty($photos)): ?>
          <p class="small text-muted mb-0">Sem fotos registadas.</p>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($photos as $photo): ?>
              <a href="<?= e('/' . ltrim((string) ($photo['image_path'] ?? ''), '/')) ?>" target="_blank" rel="noopener">
                <img src="<?= e('/' . ltrim((string) ($photo['image_path'] ?? ''), '/')) ?>" alt="Foto" style="width:72px;height:72px;object-fit:cover;border-radius:8px;<?= (int) ($photo['is_primary'] ?? 0) === 1 ? 'border:2px solid #0d6efd;' : '' ?>">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="rd-card h-100">
      <div class="card-body">
        <h6>Histórico de moderação</h6>
        <?php if (empty($moderationActions)): ?>
          <p class="small text-muted mb-0">Sem acções de moderação.</p>
        <?php else: ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($moderationActions as $action): ?>
              <li class="mb-2 pb-2 border-bottom">
                <strong><?= e((string) ($action['action_type'] ?? '')) ?></strong>
                · <?= e((string) ($action['reason'] ?? '')) ?><br>
                <span class="text-muted">Admin: <?= e((string) ($action['admin_name'] ?? '')) ?> · <?= e((string) ($action['created_at'] ?? '')) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
