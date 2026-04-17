<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-heart"></i></span>Seus Matches</h3>
    <p class="rd-page-header__subtitle">Conexões ativas com acesso a conversa, perfil e evolução para Encontro Seguro.</p>
  </div>
</div>
<?php if (empty($matches ?? [])): ?>
<?php $title='Sem matches ainda'; $description='Continue curtindo perfis para criar conexões.'; require dirname(__DIR__).'/partials/empty-state.php'; ?>
<?php else: ?>
<?php $safeDateEligibleMap = is_array($safe_date_eligible_map ?? null) ? $safe_date_eligible_map : []; ?>
<?php $safeDateCapabilitiesMap = is_array($safe_date_capabilities_map ?? null) ? $safe_date_capabilities_map : []; ?>
<div class="row g-3">
<?php foreach (($matches ?? []) as $match): ?>
  <?php
    $counterpartId = (int) ($match['counterpart_id'] ?? 0);
    $counterpartName = (string) ($match['counterpart_name'] ?? ('Utilizador #' . $counterpartId));
    $counterpartPhoto = (string) ($match['counterpart_photo'] ?? '');
    $canProposeSafeDate = $counterpartId > 0 && !empty($safeDateEligibleMap[$counterpartId]);
    $safeDateCapabilities = $counterpartId > 0 && isset($safeDateCapabilitiesMap[$counterpartId]) && is_array($safeDateCapabilitiesMap[$counterpartId]) ? $safeDateCapabilitiesMap[$counterpartId] : [];
    $safeDateMetaData = safe_date_capability_meta($safeDateCapabilities, 'matches');
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="rd-card h-100">
      <div class="card-body">
        <div class="rd-card-header">
          <div class="d-flex gap-2 align-items-center">
            <?php if ($counterpartPhoto !== ''): ?><img src="<?= e(url($counterpartPhoto)) ?>" alt="foto do match" class="rd-eligible-card__photo"><?php else: ?><div class="rd-eligible-card__photo rd-eligible-card__photo--placeholder"><i class="fa-solid fa-user"></i></div><?php endif; ?>
            <div><h6 class="mb-0"><a href="/member/<?= $counterpartId ?>" class="text-decoration-none"><?= e($counterpartName) ?></a></h6><p class="rd-meta-text mb-0">Status: <?= e((string) ($match['status'] ?? 'active')) ?></p></div>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="/member/<?= $counterpartId ?>" class="btn btn-sm btn-rd-soft"><i class="fa-solid fa-id-card me-1"></i>Ver perfil</a>
          <a href="/messages" class="btn btn-sm btn-rd-primary"><i class="fa-solid fa-comments me-1"></i>Conversar</a>
          <?php if ($canProposeSafeDate): ?><a href="/dates?invitee_user_id=<?= $counterpartId ?>" class="btn btn-sm btn-outline-success" title="<?= e($safeDateMetaData['summary']) ?>"><i class="fa-solid fa-shield-heart me-1"></i>Encontro Seguro</a><?php endif; ?>
        </div>
        <?php if ($canProposeSafeDate): ?><div class="rd-safe-capability-note mt-2"><span class="small text-muted"><?= e($safeDateMetaData['context']) ?></span><div class="rd-safe-capability-badges mt-1"><?php foreach ($safeDateMetaData['labels'] as $label): ?><span class="rd-safe-pill"><?= e($label) ?></span><?php endforeach; ?></div></div><?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
