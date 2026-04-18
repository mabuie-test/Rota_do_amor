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

  <div class="rd-soft-panel mb-3">
    <div class="fw-semibold mb-1">Checklist antes de enviar</div>
    <ul class="small mb-0 ps-3">
      <li>Documento legível e sem corte.</li>
      <li>Selfie com rosto visível e mesma pessoa do documento.</li>
      <li>Evite reflexos, desfoque ou baixa iluminação.</li>
    </ul>
  </div>

  <form method="post" action="/verification/submit" class="row g-3" enctype="multipart/form-data" data-upload-fallback="multi-single"><?= csrf_field() ?>
    <div class="col-md-6">
      <label class="form-label">Documento de identidade</label>
      <div class="rd-upload-drop">
        <input required class="form-control" type="file" name="document_image" accept="image/jpeg,image/png,image/webp">
        <input type="hidden" name="document_image_data_url" value="">
      </div>
      <div class="form-text">JPG, PNG ou WEBP.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Selfie segurando o documento</label>
      <div class="rd-upload-drop">
        <input required class="form-control" type="file" name="selfie_image" accept="image/jpeg,image/png,image/webp">
        <input type="hidden" name="selfie_image_data_url" value="">
      </div>
      <div class="form-text">Imagem nítida e bem iluminada.</div>
    </div>
    <div class="col-12"><button class="btn btn-rd-primary">Enviar para análise</button></div>
  </form>
</div></div>
