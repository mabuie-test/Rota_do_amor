<?php
$mode = $connection_mode ?? [];
$intentionOptions = $intention_options ?? [];
$paceOptions = $pace_options ?? [];
$opennessOptions = $openness_options ?? [];
$interestNames = array_map(static fn(array $row): string => (string) ($row['interest_name'] ?? ''), $interests ?? []);
$preferences = $preferences ?? [];
$profileChecklist = $profile_checklist ?? [];
$signals = $completion_signals ?? [];
$profilePhotoPath = trim((string) ($profile['profile_photo_path'] ?? ''));
$profileName = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
?>
<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-user-pen"></i></span>Meu Perfil</h3>
    <p class="rd-page-header__subtitle">Gestão de identidade, preferências, modo relacional e ativos visuais em experiência premium.</p>
  </div>
</div>

<?php if (!empty($profile)): ?>
<div class="rd-card rd-profile-card rd-profile-hero mb-3">
  <div class="card-body">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <?php if ($profilePhotoPath !== ''): ?>
          <img
            src="<?= e(url($profilePhotoPath)) ?>"
            alt="Foto de perfil de <?= e($profileName !== '' ? $profileName : 'utilizador') ?>"
            class="rd-profile-hero__avatar"
          >
        <?php else: ?>
          <div class="avatar"><?= e(strtoupper(substr((string) ($profile['first_name'] ?? 'U'),0,1))) ?></div>
        <?php endif; ?>
        <div>
          <h4 class="mb-1 text-white rd-serif"><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h4>
          <p class="mb-2 text-white-50"><i class="fa-solid fa-location-dot me-1"></i><?= e(($profile['city_name'] ?? '') . ', ' . ($profile['province_name'] ?? '')) ?></p>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach (($badges ?? []) as $badge): $kind = $badge['badge_type']; $label = ucfirst($badge['badge_type']); require dirname(__DIR__).'/partials/badge.php'; endforeach; ?>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-start align-self-md-center">
        <div class="rd-profile-hero__metric"><span class="label">Completude</span><span class="value"><?= (int) ($profile_completion_percent ?? 0) ?>%</span></div>
        <div class="rd-profile-hero__metric"><span class="label">Atratividade</span><span class="value"><?= (int) ($profile_attractiveness_percent ?? 0) ?>%</span></div>
        <div class="rd-profile-hero__metric"><span class="label">Confiança</span><span class="value"><?= e((string) ($trust_indicator ?? 'Baixa')) ?></span></div>
      </div>
    </div>

    <div class="mt-3">
      <div class="progress mb-2"><div class="progress-bar" role="progressbar" style="width: <?= (int) ($profile_completion_percent ?? 0) ?>%"></div></div>
      <div class="rd-stat-grid">
        <?php foreach ($profileChecklist as $item => $ok): ?>
          <div class="rd-pill-check <?= $ok ? 'is-ok' : '' ?>">
            <span><?= e((string) $item) ?></span>
            <strong><?= $ok ? '✓' : '—' ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-2 mt-1">
      <div class="col-6 col-lg-3"><div class="rd-soft-panel"><div class="small text-white-50">Fotos guardadas</div><div class="fw-semibold"><?= (int) ($signals['photos_count'] ?? 0) ?></div></div></div>
      <div class="col-6 col-lg-3"><div class="rd-soft-panel"><div class="small text-white-50">Interesses guardados</div><div class="fw-semibold"><?= (int) ($signals['interests_count'] ?? 0) ?></div></div></div>
      <div class="col-6 col-lg-3"><div class="rd-soft-panel"><div class="small text-white-50">Preferências</div><div class="fw-semibold"><?= (int) ($signals['preferences_count'] ?? 0) > 0 ? 'Guardadas' : 'Pendentes' ?></div></div></div>
      <div class="col-6 col-lg-3"><div class="rd-soft-panel"><div class="small text-white-50">Identidade</div><div class="fw-semibold"><?= (int) ($signals['identity_verified'] ?? 0) > 0 ? 'Aprovada' : 'Não aprovada' ?></div></div></div>
    </div>

    <?php if (!empty($profile_missing_items)): ?>
      <div class="alert alert-warning py-2 mt-3 mb-0"><strong>Falta concluir:</strong> <?= e(implode(', ', $profile_missing_items)) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="rd-card rd-section-card">
  <div class="card-body">
    <h5 class="rd-section-title"><i class="fa-solid fa-id-card"></i>Dados principais</h5>
    <p class="rd-section-subtitle">Atualize os elementos que deixam o seu perfil mais claro, humano e atrativo.</p>
    <form method="post" action="/profile/update" class="row g-3"><?= csrf_field() ?>
      <div class="col-12"><label class="form-label">Bio</label><textarea class="form-control" rows="4" name="bio" maxlength="1200" placeholder="Conte um pouco sobre si, estilo de vida e valores."><?= e((string) ($profile['bio'] ?? '')) ?></textarea></div>
      <div class="col-md-6"><label class="form-label">Profissão</label><input class="form-control" name="profession" value="<?= e((string) ($profile['profession'] ?? '')) ?>"></div>
      <div class="col-md-6"><label class="form-label">Educação</label><input class="form-control" name="education" value="<?= e((string) ($profile['education'] ?? '')) ?>"></div>
      <div class="col-12 d-flex justify-content-end"><button class="btn btn-rd-primary">Guardar dados</button></div>
    </form>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="rd-card rd-section-card h-100">
      <div class="card-body">
        <h5 class="rd-section-title"><i class="fa-solid fa-star"></i>Interesses</h5>
        <p class="rd-section-subtitle">Adicione pelo menos 3 interesses, separados por vírgula, ponto e vírgula ou nova linha.</p>
        <form method="post" action="/profile/interests"><?= csrf_field() ?>
          <textarea class="form-control mb-2" rows="4" name="interests" placeholder="Ex.: viagens, leitura, desporto"><?= e(implode(', ', $interestNames)) ?></textarea>
          <button class="btn btn-rd-primary btn-sm">Guardar interesses</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="rd-card rd-section-card h-100">
      <div class="card-body">
        <h5 class="rd-section-title"><i class="fa-solid fa-sliders"></i>Preferências de descoberta</h5>
        <p class="rd-section-subtitle">Defina filtros e intenções para receber sugestões mais alinhadas com o seu momento.</p>
        <form method="post" action="/profile/preferences" class="row g-2"><?= csrf_field() ?>
          <div class="col-md-6"><label class="form-label small">Interessado em</label><select class="form-select" name="interested_in"><option value="all" <?= (($preferences['interested_in'] ?? 'all') === 'all') ? 'selected' : '' ?>>Todos</option><option value="male" <?= (($preferences['interested_in'] ?? '') === 'male') ? 'selected' : '' ?>>Homens</option><option value="female" <?= (($preferences['interested_in'] ?? '') === 'female') ? 'selected' : '' ?>>Mulheres</option></select></div>
          <div class="col-md-3"><label class="form-label small">Idade min</label><input type="number" class="form-control" min="18" max="90" name="age_min" value="<?= (int) ($preferences['age_min'] ?? 18) ?>"></div>
          <div class="col-md-3"><label class="form-label small">Idade máx</label><input type="number" class="form-control" min="18" max="99" name="age_max" value="<?= (int) ($preferences['age_max'] ?? 70) ?>"></div>
          <div class="col-md-6"><label class="form-label small">Objetivo preferido</label><select class="form-select" name="preferred_goal"><option value="any" <?= (($preferences['preferred_goal'] ?? 'any') === 'any') ? 'selected' : '' ?>>Qualquer</option><option value="friendship" <?= (($preferences['preferred_goal'] ?? '') === 'friendship') ? 'selected' : '' ?>>Amizade</option><option value="dating" <?= (($preferences['preferred_goal'] ?? '') === 'dating') ? 'selected' : '' ?>>Namoro</option><option value="marriage" <?= (($preferences['preferred_goal'] ?? '') === 'marriage') ? 'selected' : '' ?>>Casamento</option></select></div>
          <div class="col-md-6"><label class="form-label small">Província preferida</label><select class="form-select" name="preferred_province_id"><option value="">Sem preferência</option><?php foreach (($provinces ?? []) as $province): ?><option value="<?= (int) $province['id'] ?>" <?= (int) ($preferences['preferred_province_id'] ?? 0) === (int) $province['id'] ? 'selected' : '' ?>><?= e((string) $province['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label small">Cidade preferida</label><select class="form-select" name="preferred_city_id"><option value="">Sem preferência</option><?php foreach (($cities ?? []) as $city): ?><option value="<?= (int) $city['id'] ?>" <?= (int) ($preferences['preferred_city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>><?= e((string) $city['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-12"><button class="btn btn-rd-primary btn-sm">Guardar preferências</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="rd-card rd-section-card mt-3">
  <div class="card-body">
    <h5 class="rd-section-title"><i class="fa-solid fa-camera"></i>Fotos do perfil</h5>
    <p class="rd-section-subtitle">Mantenha a sua identidade visual atualizada com imagem principal e galeria.</p>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <form method="post" action="/profile/photo" enctype="multipart/form-data" class="rd-upload-drop"><?= csrf_field() ?>
          <label class="form-label">Atualizar foto principal</label>
          <input required class="form-control mb-2" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
          <button class="btn btn-rd-primary btn-sm">Enviar foto principal</button>
        </form>
      </div>
      <div class="col-md-6">
        <form method="post" action="/profile/gallery" enctype="multipart/form-data" class="rd-upload-drop"><?= csrf_field() ?>
          <label class="form-label">Adicionar à galeria</label>
          <input required class="form-control mb-2" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
          <button class="btn btn-outline-primary btn-sm">Adicionar foto</button>
        </form>
      </div>
    </div>

    <div class="rd-media-grid">
      <?php foreach (($photos ?? []) as $photo): ?>
        <div class="rd-photo-card">
          <img src="<?= e(url((string) $photo['image_path'])) ?>" alt="foto do perfil">
          <div class="small my-2"><?= (int) ($photo['is_primary'] ?? 0) === 1 ? 'Principal' : 'Galeria' ?></div>
          <form method="post" action="/profile/photo/primary" class="mb-1"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="btn btn-sm btn-outline-primary w-100" <?= (int) ($photo['is_primary'] ?? 0) === 1 ? 'disabled' : '' ?>>Tornar principal</button></form>
          <form method="post" action="/profile/photo/delete"><?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>"><button class="btn btn-sm btn-outline-danger w-100">Remover</button></form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="rd-card rd-section-card mt-3">
  <div class="card-body">
    <h5 class="rd-section-title"><i class="fa-solid fa-heart-pulse"></i>Modo do Coração</h5>
    <p class="rd-section-subtitle">Atualize sua intenção e ritmo do momento para conexões mais autênticas.</p>

    <form method="post" action="/profile/connection-mode" class="row g-3"><?= csrf_field() ?>
      <div class="col-md-6"><label class="form-label small fw-semibold">Intenção atual</label><select class="form-select" name="current_intention" required><?php foreach ($intentionOptions as $key => $item): ?><option value="<?= e((string) $key) ?>" <?= (($mode['current_intention'] ?? '') === $key) ? 'selected' : '' ?>><?= e((string) $item['label']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Ritmo relacional</label><select class="form-select" name="relational_pace" required><?php foreach ($paceOptions as $key => $item): ?><option value="<?= e((string) $key) ?>" <?= (($mode['relational_pace'] ?? '') === $key) ? 'selected' : '' ?>><?= e((string) $item['label']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Abertura emocional (opcional)</label><select class="form-select" name="openness_level"><option value="">Prefiro não indicar</option><?php foreach ($opennessOptions as $key => $label): ?><option value="<?= e((string) $key) ?>" <?= (($mode['openness_level'] ?? '') === $key) ? 'selected' : '' ?>><?= e((string) $label) ?></option><?php endforeach; ?></select></div>
      <div class="col-12 d-flex justify-content-end"><button class="btn btn-rd-primary"><i class="fa-solid fa-hand-holding-heart me-2"></i>Guardar modo</button></div>
    </form>
  </div>
</div>
<?php else: ?>
<?php $title='Perfil não encontrado'; $description='Complete seu registo e volte novamente.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php endif; ?>
