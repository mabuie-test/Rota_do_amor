<div class="rd-page-header">
  <div><h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-compass"></i></span>Descobrir Pessoas</h3><p class="rd-page-header__subtitle">Sugestões com intenção, ritmo e compatibilidade contextual.</p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-rd-soft" href="/invites/received"><i class="fa-solid fa-envelope-open-heart me-1"></i>Convites Recebidos</a>
    <a class="btn btn-sm btn-rd-soft" href="/invites/sent"><i class="fa-solid fa-paper-plane me-1"></i>Convites Enviados</a>
  </div>
</div>
<form method="get" class="rd-form-section d-flex gap-2 align-items-center flex-wrap mb-3">
  <input class="form-control form-control-sm" style="width:96px" type="number" name="age_min" placeholder="Idade min" value="<?= e((string) ($filters['age_min'] ?? '')) ?>">
  <input class="form-control form-control-sm" style="width:96px" type="number" name="age_max" placeholder="Idade max" value="<?= e((string) ($filters['age_max'] ?? '')) ?>">
  <select class="form-select form-select-sm" name="relationship_goal">
    <option value="">Objectivo</option>
    <?php foreach (['friendship' => 'Amizade', 'dating' => 'Namoro', 'marriage' => 'Casamento'] as $key => $label): ?>
      <option value="<?= e($key) ?>" <?= (($filters['relationship_goal'] ?? '') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="small rd-supporting-text"><i class="fa-solid fa-circle-check me-1"></i><input type="checkbox" name="verified_only" value="1" <?= !empty($filters['verified_only']) ? 'checked' : '' ?>> Verificado</label>
  <button class="btn btn-sm btn-rd-soft"><i class="fa-solid fa-sliders me-2"></i>Filtrar</button>
</form>

<div class="row g-3">
<?php if (empty($profiles ?? [])): ?>
  <div class="col-12"><?php $title='Sem sugestões por agora'; $description='Volte em alguns instantes para novos perfis.'; require dirname(__DIR__).'/partials/empty-state.php'; ?></div>
<?php endif; ?>
<?php foreach (($profiles ?? []) as $profile): ?>
  <div class="col-md-6 col-xl-4">
    <div class="rd-card rd-discovery-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <div class="rd-name"><?= e(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></div>
            <p class="rd-meta mb-1"><i class="fa-solid fa-location-dot me-1"></i><?= e((string) ($profile['city_name'] ?? '')) ?>, <?= e((string) ($profile['province_name'] ?? '')) ?></p>
          </div>
          <span class="rd-badge rd-compat-pill">Compatibilidade <?= (int) (($profile['_compatibility'] ?? 0)) ?>%</span>
        </div>

        <p class="small mb-2"><i class="fa-solid fa-star me-1"></i><?= e((string) ($profile['relationship_goal'] ?? '')) ?></p>
        <p class="small text-muted mb-2">Convidar para Conversa: envie uma abertura com intenção real.</p>

        <div class="rd-heart-mode-card mb-3">
          <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($profile['_intention_icon'] ?? 'fa-heart-pulse')) ?>"></i><?= e((string) ($profile['_intention_label'] ?? 'Conhecer sem pressão')) ?></span>
            <span class="rd-heart-chip"><i class="fa-solid <?= e((string) ($profile['_pace_icon'] ?? 'fa-wave-square')) ?>"></i><?= e((string) ($profile['_pace_label'] ?? 'Equilibrado')) ?></span>
          </div>
          <div class="small text-muted">
            Intenção: <strong><?= e((string) ($profile['_intention_alignment_label'] ?? 'Moderada')) ?></strong> ·
            Ritmo: <strong><?= e((string) ($profile['_pace_alignment_label'] ?? 'Moderada')) ?></strong>
          </div>
        </div>

        <div class="d-grid gap-2 mb-2">
          <button
            class="btn btn-sm btn-rd-primary"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#inviteFormStandard<?= (int) ($profile['id'] ?? 0) ?>"
            aria-expanded="false"
            aria-controls="inviteFormStandard<?= (int) ($profile['id'] ?? 0) ?>">
            <i class="fa-solid fa-envelope me-1"></i>Enviar Convite
          </button>
          <button
            class="btn btn-sm btn-outline-warning"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#inviteFormPriority<?= (int) ($profile['id'] ?? 0) ?>"
            aria-expanded="false"
            aria-controls="inviteFormPriority<?= (int) ($profile['id'] ?? 0) ?>">
            <i class="fa-solid fa-crown me-1"></i>Convite Prioritário
          </button>
        </div>

        <div class="collapse mb-2" id="inviteFormStandard<?= (int) ($profile['id'] ?? 0) ?>">
          <form method="post" action="/invites/send"><?= csrf_field() ?>
            <input type="hidden" name="receiver_user_id" value="<?= (int) ($profile['id'] ?? 0) ?>">
            <input type="hidden" name="invitation_type" value="standard">
            <textarea name="opening_message" maxlength="500" rows="2" class="form-control form-control-sm mb-2" placeholder="Mensagem de abertura (opcional)"></textarea>
            <button class="btn btn-sm btn-rd-primary w-100"><i class="fa-solid fa-paper-plane me-1"></i>Confirmar envio</button>
          </form>
        </div>

        <div class="collapse mb-2" id="inviteFormPriority<?= (int) ($profile['id'] ?? 0) ?>">
          <form method="post" action="/invites/send"><?= csrf_field() ?>
            <input type="hidden" name="receiver_user_id" value="<?= (int) ($profile['id'] ?? 0) ?>">
            <input type="hidden" name="invitation_type" value="priority">
            <textarea name="opening_message" maxlength="500" rows="2" class="form-control form-control-sm mb-2" placeholder="Convite prioritário: mensagem obrigatória"></textarea>
            <button class="btn btn-sm btn-outline-warning w-100"><i class="fa-solid fa-crown me-1"></i>Confirmar prioritário</button>
          </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
          <div>
            <?php if (!empty($profile['_intention_is_aligned'])): ?>
              <span class="rd-badge badge-aligned"><i class="fa-solid fa-sparkles"></i>Intenção alinhada</span>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-outline-danger" title="Gostar"><i class="fa-solid fa-heart"></i></button>
            <button class="btn btn-sm btn-outline-primary" title="Super destaque"><i class="fa-solid fa-star"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
