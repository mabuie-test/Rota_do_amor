<?php
$mode = $connection_mode ?? [];
$intentionOptions = $intention_options ?? [];
$paceOptions = $pace_options ?? [];
$opennessOptions = $openness_options ?? [];
?>
<h3 class="mb-3">Meu Perfil</h3>
<?php if (!empty($profile)): ?>
<div class="rd-card rd-profile-card">
  <div class="card-body">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div class="avatar"><?= e(strtoupper(substr((string) ($profile['first_name'] ?? 'U'),0,1))) ?></div>
      <div>
        <h5 class="mb-1 rd-serif"><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
        <p class="text-muted mb-1"><i class="fa-solid fa-location-dot me-1"></i><?= e(($profile['city_name'] ?? '') . ', ' . ($profile['province_name'] ?? '')) ?></p>
        <?php foreach (($badges ?? []) as $badge): $kind = $badge['badge_type']; $label = ucfirst($badge['badge_type']); require dirname(__DIR__).'/partials/badge.php'; endforeach; ?>
      </div>
    </div>
    <p class="mb-0"><?= e($profile['bio'] ?? 'Adicione uma bio para melhorar seu perfil.') ?></p>
  </div>
</div>

<div class="rd-card mt-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <h5 class="mb-1 rd-serif"><i class="fa-solid fa-heart-pulse me-2"></i>Modo do Coração</h5>
        <p class="small text-muted mb-0">Atualize sua intenção e ritmo do momento. Isso melhora contexto, ranking e alinhamento humano.</p>
      </div>
      <div class="rd-heart-mode-card">
        <div class="small text-muted mb-2">Estado atual</div>
        <div class="d-flex flex-wrap gap-2">
          <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($mode['intention_icon'] ?? 'fa-heart-pulse')) ?>"></i><?= e((string) ($mode['intention_label'] ?? 'Conhecer sem pressão')) ?></span>
          <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($mode['pace_icon'] ?? 'fa-wave-square')) ?>"></i><?= e((string) ($mode['pace_label'] ?? 'Equilibrado')) ?></span>
        </div>
      </div>
    </div>
    <form method="post" action="/profile/connection-mode" class="row g-3"><?= csrf_field() ?>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Intenção atual</label>
        <select class="form-select" name="current_intention" required>
          <?php foreach ($intentionOptions as $key => $item): ?>
            <option value="<?= e((string) $key) ?>" <?= (($mode['current_intention'] ?? '') === $key) ? 'selected' : '' ?>><?= e((string) $item['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Ritmo relacional</label>
        <select class="form-select" name="relational_pace" required>
          <?php foreach ($paceOptions as $key => $item): ?>
            <option value="<?= e((string) $key) ?>" <?= (($mode['relational_pace'] ?? '') === $key) ? 'selected' : '' ?>><?= e((string) $item['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Abertura emocional (opcional)</label>
        <select class="form-select" name="openness_level">
          <option value="">Prefiro não indicar</option>
          <?php foreach ($opennessOptions as $key => $label): ?>
            <option value="<?= e((string) $key) ?>" <?= (($mode['openness_level'] ?? '') === $key) ? 'selected' : '' ?>><?= e((string) $label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-rd-primary"><i class="fa-solid fa-hand-holding-heart me-2"></i>Guardar modo</button>
      </div>
    </form>
  </div>
</div>

<div class="rd-card mt-3">
  <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h5 class="mb-1 rd-serif"><i class="fa-solid fa-envelope-open-heart me-2"></i>Convites com Intenção</h5>
      <p class="small text-muted mb-0">No seu perfil público, outros membros podem usar o botão <strong>Convidar para Conversa</strong> para abrir conexões mais humanas.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/invites/received" class="btn btn-sm btn-rd-primary"><i class="fa-solid fa-heart me-1"></i>Quem gostou de mim</a>
      <a href="/invites/sent" class="btn btn-sm btn-rd-soft"><i class="fa-solid fa-paper-plane me-1"></i>Convites enviados</a>
    </div>
  </div>
</div>

<div class="rd-card mt-3">
  <div class="card-body">
    <h6>Galeria</h6>
    <div class="row g-2 mb-3">
      <?php foreach (($photos ?? []) as $photo): ?>
        <div class="col-md-3">
          <div class="border rounded p-2">
            <div class="small text-truncate mb-1"><?= e((string) $photo['image_path']) ?></div>
            <div class="small mb-2"><?= (int) ($photo['is_primary'] ?? 0) === 1 ? 'Principal' : 'Galeria' ?></div>
            <form method="post" action="/profile/photo/primary" class="mb-1"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="btn btn-sm btn-outline-primary w-100">Tornar principal</button></form>
            <form method="post" action="/profile/photo/delete"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="btn btn-sm btn-outline-danger w-100">Remover</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php else: ?>
<?php $title='Perfil não encontrado'; $description='Complete seu registo e volte novamente.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php endif; ?>
