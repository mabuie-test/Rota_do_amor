<?php $selectedStoryId = (int) ($selected_story_id ?? 0); ?>
<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-feather-pointed"></i></span>Histórias Anónimas</h3>
    <p class="rd-page-header__subtitle">Narrativas da comunidade com reação empática, comentário e moderação responsável.</p>
  </div>
</div>
<div class="rd-card mb-3"><div class="card-body">
  <div class="rd-card-header"><div><h6 class="rd-card-header__title"><i class="fa-solid fa-pen-to-square"></i>Publicar história</h6><p class="rd-card-header__subtitle">Partilha segura e anónima com categoria contextual.</p></div></div>
  <form method="post" action="/stories/anonymous" class="row g-2 rd-form-section"><?= csrf_field() ?>
    <div class="col-md-3"><select class="form-select" name="category"><option value="relacoes">Relações</option><option value="amor">Amor</option><option value="encontros">Encontros</option><option value="duvidas">Dúvidas</option><option value="ciumes">Ciúmes</option><option value="red_flags">Red flags</option><option value="green_flags">Green flags</option></select></div>
    <div class="col-md-9"><input class="form-control" name="title" maxlength="120" placeholder="Título opcional"></div>
    <div class="col-12"><textarea class="form-control" rows="3" name="content" required minlength="20" maxlength="1500" placeholder="Partilha tua história de forma anónima..."></textarea></div>
    <div class="col-12"><button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane me-1"></i>Publicar anonimamente</button></div>
  </form>
</div></div>

<?php foreach (($stories ?? []) as $story): ?>
<?php $storyId = (int) ($story['id'] ?? 0); ?>
<div class="rd-card mb-3 <?= $selectedStoryId === $storyId ? 'rd-story-highlight' : '' ?>" id="story-<?= $storyId ?>"><div class="card-body">
  <p class="rd-meta-text mb-1"><i class="fa-solid fa-tag me-1"></i><?= e((string) ($story['category'] ?? 'relacoes')) ?> · <?= e((string) ($story['created_at'] ?? '')) ?> <?php if ((int) ($story['is_featured'] ?? 0) === 1): ?><span class="rd-badge badge-verified"><i class="fa-solid fa-star"></i>Destaque</span><?php endif; ?></p>
  <?php if (!empty($story['title'])): ?><h6><?= e((string) $story['title']) ?></h6><?php endif; ?>
  <p><?= nl2br(e((string) ($story['content'] ?? ''))) ?></p>
  <div class="d-flex gap-2 flex-wrap mb-2">
    <?php foreach (['apoio'=>'Apoio','empatia'=>'Empatia','concordo'=>'Concordo','discordo'=>'Discordo','curioso'=>'Curioso'] as $key=>$label): ?>
      <form method="post" action="/stories/anonymous/react"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= $storyId ?>"><input type="hidden" name="reaction_type" value="<?= e($key) ?>"><button class="btn btn-sm btn-outline-primary"><i class="fa-regular fa-face-smile me-1"></i><?= e($label) ?></button></form>
    <?php endforeach; ?>
  </div>
  <p class="rd-supporting-text">Reações: <strong><?= (int) ($story['reactions_count'] ?? 0) ?></strong> · Comentários: <strong><?= (int) ($story['comments_count'] ?? 0) ?></strong></p>
  <?php foreach (($story['comments_preview'] ?? []) as $comment): ?><p class="small text-muted mb-1"><i class="fa-solid fa-comment-dots me-1"></i><?= e((string) ($comment['comment_text'] ?? '')) ?></p><?php endforeach; ?>
  <form method="post" action="/stories/anonymous/comment" class="d-flex gap-2 mb-2"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= $storyId ?>"><input class="form-control" name="comment" placeholder="Comentar" maxlength="500"><button class="btn btn-sm btn-rd-soft"><i class="fa-solid fa-reply me-1"></i>Enviar</button></form>
  <form method="post" action="/stories/anonymous/report" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="story_id" value="<?= $storyId ?>"><input class="form-control form-control-sm" name="reason" placeholder="Motivo da denúncia"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-flag me-1"></i>Denunciar</button></form>
</div></div>
<?php endforeach; ?>
