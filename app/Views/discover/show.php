<?php if (empty($profile)): ?>
<div class="alert alert-warning">Perfil indisponível.</div>
<?php else: ?>
<?php
$fullName = trim((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
$photos = $profile['photos'] ?? [];
$primaryPhoto = $profile['profile_photo_path'] ?? ($photos[0]['image_path'] ?? null);
$badges = $profile['badges'] ?? [];
$posts = $profile['recent_posts'] ?? [];
$targetId = (int) ($profile['id'] ?? 0);
?>
<div class="rd-card mb-3"><div class="card-body">
  <div class="d-flex flex-column flex-lg-row gap-4 align-items-start">
    <div class="text-center">
      <?php if ($primaryPhoto): ?>
        <a href="<?= e(url((string) $primaryPhoto)) ?>" data-lightbox-group="profile-gallery" data-lightbox-index="0">
          <img src="<?= e(url((string) $primaryPhoto)) ?>" class="rd-profile-hero-photo" alt="foto principal">
        </a>
      <?php else: ?>
        <div class="rd-profile-hero-placeholder"><i class="fa-solid fa-user"></i></div>
      <?php endif; ?>
      <div class="small text-muted mt-2">Clique para ampliar</div>
    </div>

    <div class="flex-grow-1">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <div>
          <h3 class="mb-1"><?= e($fullName !== '' ? $fullName : ('Utilizador #' . $targetId)) ?></h3>
          <p class="small text-muted mb-0"><?= e((string) ($profile['city_name'] ?? '')) ?> · <?= e((string) ($profile['relationship_goal'] ?? '')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if ((int) ($profile['is_verified'] ?? 0) === 1): ?><span class="rd-badge badge-verified"><i class="fa-solid fa-badge-check"></i>Verificado</span><?php endif; ?>
          <?php if ((int) ($profile['has_premium'] ?? 0) === 1): ?><span class="rd-badge badge-premium"><i class="fa-solid fa-crown"></i>Premium</span><?php endif; ?>
          <?php if ((int) ($profile['boost_active'] ?? 0) === 1): ?><span class="rd-badge badge-boosted"><i class="fa-solid fa-bolt"></i>Em destaque</span><?php endif; ?>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="rd-soft-panel"><div class="small text-muted">Compatibilidade</div><strong><?= number_format((float) ($profile['_compatibility'] ?? 0), 1) ?>%</strong></div></div>
        <div class="col-md-4"><div class="rd-soft-panel"><div class="small text-muted">Intenção atual</div><strong><?= e((string) ($profile['_intention_label'] ?? 'Não informado')) ?></strong></div></div>
        <div class="col-md-4"><div class="rd-soft-panel"><div class="small text-muted">Ritmo relacional</div><strong><?= e((string) ($profile['_pace_label'] ?? 'Não informado')) ?></strong></div></div>
      </div>

      <p class="mb-3"><?= nl2br(e((string) ($profile['bio'] ?? 'Sem bio.'))) ?></p>

      <div class="d-flex flex-wrap gap-2 rd-profile-actions" data-target-user="<?= $targetId ?>">
        <form method="post" action="/invites/send"><?= csrf_field() ?><input type="hidden" name="receiver_user_id" value="<?= $targetId ?>"><button class="btn btn-sm btn-rd-primary"><i class="fa-solid fa-envelope-open-heart me-1"></i>Convidar</button></form>
        <button class="btn btn-sm btn-rd-soft" data-action="favorite"><i class="fa-solid fa-star me-1"></i>Favoritar</button>
        <button class="btn btn-sm btn-outline-danger" data-action="block"><i class="fa-solid fa-ban me-1"></i>Bloquear</button>
        <button class="btn btn-sm btn-outline-warning" data-action="report"><i class="fa-solid fa-flag me-1"></i>Denunciar</button>
        <a class="btn btn-sm btn-outline-secondary" href="#member-posts"><i class="fa-solid fa-newspaper me-1"></i>Ver publicações</a>
        <a class="btn btn-sm btn-outline-secondary" href="/discover"><i class="fa-solid fa-compass me-1"></i>Voltar</a>
      </div>
    </div>
  </div>
</div></div>

<div class="rd-card mb-3"><div class="card-body">
  <h5 class="mb-3"><i class="fa-regular fa-images me-2"></i>Galeria</h5>
  <?php if (!empty($photos)): ?>
    <div class="row g-2">
      <?php foreach ($photos as $index => $photo): ?>
        <div class="col-md-3 col-6">
          <a href="<?= e(url((string) ($photo['image_path'] ?? ''))) ?>" data-lightbox-group="profile-gallery" data-lightbox-index="<?= (int) $index ?>">
            <img src="<?= e(url((string) ($photo['image_path'] ?? ''))) ?>" class="img-fluid rounded border" alt="foto da galeria">
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted small mb-0">Este membro ainda não adicionou galeria.</p>
  <?php endif; ?>
</div></div>

<div class="rd-card" id="member-posts"><div class="card-body">
  <h5 class="mb-3"><i class="fa-solid fa-newspaper me-2"></i>Publicações recentes</h5>
  <?php if (!empty($posts)): ?>
    <div class="d-flex flex-column gap-2">
      <?php foreach ($posts as $post): ?>
        <div class="rd-list-item">
          <?php if (!empty($post['content'])): ?><p class="mb-2"><?= e((string) $post['content']) ?></p><?php endif; ?>
          <?php if (!empty($post['first_image_path'])): ?>
            <a href="<?= e(url((string) $post['first_image_path'])) ?>" data-lightbox-group="member-posts" data-lightbox-index="<?= (int) $post['id'] ?>">
              <img src="<?= e(url((string) (($post['first_thumbnail_path'] ?? '') !== '' ? $post['first_thumbnail_path'] : $post['first_image_path']))) ?>" class="img-fluid rounded border mb-2 rd-member-post-preview" alt="imagem da publicação">
            </a>
          <?php endif; ?>
          <div class="small text-muted d-flex flex-wrap gap-2 mb-2">
            <span>❤️ <?= (int) ($post['likes_count'] ?? 0) ?></span>
            <span>💬 <?= (int) ($post['comments_count'] ?? 0) ?></span>
            <span>📎 <?= (int) ($post['images_count'] ?? 0) ?></span>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="/feed?post=<?= (int) $post['id'] ?>#post-<?= (int) $post['id'] ?>">Abrir no feed</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted small mb-0">Sem publicações recentes.</p>
  <?php endif; ?>
</div></div>
<?php endif; ?>
