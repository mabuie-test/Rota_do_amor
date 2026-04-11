<?php
$latest = $latest ?? [];
$status = (string) ($latest['status'] ?? 'not_started');
$statusLabel = match ($status) {
    'pending' => 'Pendente',
    'approved' => 'Aprovada',
    'rejected' => 'Rejeitada',
    default => 'Não iniciada',
};
$statusClass = match ($status) {
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    default => 'secondary',
};
?>
<h3 class="mb-3"><i class="fa-solid fa-id-card me-2"></i>Verificação de Identidade</h3>
<div class="rd-card"><div class="card-body">
  <p class="text-muted mb-3">Submeta documento e selfie com upload seguro para validação da equipa.</p>

  <div class="alert alert-<?= e($statusClass) ?> py-2">
    <strong>Estado atual:</strong> <?= e($statusLabel) ?>
    <?php if (!empty($latest['updated_at'])): ?> · Atualizado em <?= e((string) $latest['updated_at']) ?><?php endif; ?>
    <?php if ($status === 'rejected' && !empty($latest['rejection_reason'])): ?>
      <div class="mt-1"><strong>Motivo:</strong> <?= e((string) $latest['rejection_reason']) ?></div>
    <?php endif; ?>
  </div>

  <form method="post" action="/verification/submit" class="row g-3" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="col-md-6">
      <label class="form-label">Documento de identidade</label>
      <input required class="form-control" type="file" name="document_image" accept="image/jpeg,image/png,image/webp">
      <div class="form-text">JPG, PNG ou WEBP.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Selfie segurando o documento</label>
      <input required class="form-control" type="file" name="selfie_image" accept="image/jpeg,image/png,image/webp">
      <div class="form-text">Imagem nítida e bem iluminada.</div>
    </div>
    <div class="col-12"><button class="btn btn-rd-primary">Enviar para análise</button></div>
  </form>
</div></div>
