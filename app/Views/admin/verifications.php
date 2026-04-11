<h3 class="mb-3">Verificações de Identidade</h3>
<div class="rd-card">
  <div class="card-body table-responsive">
    <table class="table table-modern align-middle">
      <thead>
      <tr>
        <th>ID</th>
        <th>Utilizador</th>
        <th>Email</th>
        <th>Status</th>
        <th>Documento</th>
        <th>Selfie</th>
        <th>Motivo de rejeição</th>
        <th>Actualizado em</th>
        <th>Acções</th>
      </tr>
      </thead>
      <tbody>
      <?php
      $toPublicPath = static function (string $path): string {
          if ($path === '') {
              return '#';
          }
          if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
              return $path;
          }

          return '/' . ltrim($path, '/');
      };

      $isImagePath = static function (string $path): bool {
          $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
          return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
      };

      foreach (($verifications ?? []) as $v):
          $userName = trim((string) (($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')));
          $documentPath = $toPublicPath((string) ($v['document_image_path'] ?? ''));
          $selfiePath = $toPublicPath((string) ($v['selfie_image_path'] ?? ''));
          $status = (string) ($v['status'] ?? 'pending');
          $kind = $status === 'approved' ? 'verified' : ($status === 'rejected' ? 'failed' : 'pending');
          ?>
        <tr>
          <td><?= (int) $v['id'] ?></td>
          <td>
            <a href="/admin/users/<?= (int) $v['user_id'] ?>" class="fw-semibold text-decoration-none"><?= e($userName !== '' ? $userName : ('Utilizador #' . (int) $v['user_id'])) ?></a>
            <div class="small text-muted">#<?= (int) $v['user_id'] ?> · Conta: <?= e((string) ($v['user_status'] ?? 'n/a')) ?> · Premium: <?= e((string) ($v['premium_status'] ?? 'n/a')) ?></div>
          </td>
          <td><?= e((string) ($v['email'] ?? '')) ?></td>
          <td>
              <?php $label = $status; require dirname(__DIR__) . '/partials/badge.php'; ?>
            <div class="small text-muted mt-1">
              Revisado por: <?= !empty($v['reviewed_by_admin_id']) ? ('#' . (int) $v['reviewed_by_admin_id'] . ' - ' . e((string) ($v['reviewed_by_admin_name'] ?? ''))) : '—' ?>
            </div>
          </td>
          <td>
            <a href="<?= e($documentPath) ?>" target="_blank" rel="noopener">Abrir documento</a>
              <?php if ($isImagePath($documentPath)): ?>
                <div class="mt-1"><img src="<?= e($documentPath) ?>" alt="Documento" style="width:56px;height:56px;object-fit:cover;border-radius:8px;"></div>
              <?php endif; ?>
          </td>
          <td>
            <a href="<?= e($selfiePath) ?>" target="_blank" rel="noopener">Abrir selfie</a>
              <?php if ($isImagePath($selfiePath)): ?>
                <div class="mt-1"><img src="<?= e($selfiePath) ?>" alt="Selfie" style="width:56px;height:56px;object-fit:cover;border-radius:8px;"></div>
              <?php endif; ?>
          </td>
          <td><?= e((string) ($v['rejection_reason'] ?? '—')) ?></td>
          <td>
            <?= e((string) ($v['updated_at'] ?? '')) ?>
            <div class="small text-muted">Criado em: <?= e((string) ($v['created_at'] ?? '')) ?></div>
          </td>
          <td style="min-width:260px;">
            <form method="post" action="/admin/verifications/<?= (int) $v['id'] ?>/approve" class="mb-2">
                <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-success w-100" <?= $status === 'approved' ? 'disabled' : '' ?>>Aprovar</button>
            </form>
            <form method="post" action="/admin/verifications/<?= (int) $v['id'] ?>/reject" class="d-flex flex-column gap-2">
                <?= csrf_field() ?>
              <input class="form-control form-control-sm" name="reason" placeholder="Motivo da rejeição" required>
              <button class="btn btn-sm btn-outline-danger" <?= $status === 'rejected' ? 'disabled' : '' ?>>Rejeitar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
