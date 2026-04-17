<?php
$pg = $pagination ?? ['page' => 1, 'total_pages' => 1];
$viewerId = (int) ($viewer_id ?? 0);
$selectedPostId = (int) ($selected_post_id ?? 0);
$selectedCommentId = (int) ($selected_comment_id ?? 0);
$selectedTab = (string) ($selected_tab ?? 'for_you');
?>
<div class="rd-page-header">
  <div>
    <h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-sparkles"></i></span>Feed Social Inteligente</h3>
    <p class="rd-page-header__subtitle">Descoberta relacional, sinais emocionais e intenção de conexão no mesmo fluxo.</p>
  </div>
  <button class="btn btn-rd-primary" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fa-solid fa-pen-to-square me-1"></i>Criar publicação</button>
</div>

<div class="rd-feed-tabs mb-3">
  <?php foreach (($feed_tabs ?? []) as $tab): ?>
    <?php $key = (string) ($tab['key'] ?? 'for_you'); ?>
    <a href="/feed?tab=<?= e($key) ?>" class="rd-feed-tab <?= $selectedTab === $key ? 'is-active' : '' ?>"><?= e((string) ($tab['label'] ?? $key)) ?></a>
  <?php endforeach; ?>
</div>

<section class="rd-feed-hero mb-3">
  <form method="post" action="/feed/availability" class="d-flex flex-wrap gap-2 align-items-end" data-feed-availability-form>
    <?= csrf_field() ?>
    <div>
      <label class="form-label small mb-1">Disponibilidade temporária</label>
      <select name="availability_type" class="form-select form-select-sm">
        <?php foreach (($availability_types ?? []) as $availabilityType): ?>
          <option value="<?= e((string) $availabilityType) ?>"><?= e(str_replace('_', ' ', (string) $availabilityType)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label small mb-1">Duração</label>
      <select name="duration_minutes" class="form-select form-select-sm"><option value="120">2h</option><option value="180" selected>3h</option><option value="360">6h</option></select>
    </div>
    <button class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-bolt me-1"></i>Ativar estado social</button>
  </form>
</section>

<div class="modal fade" id="createPostModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rd-card">
      <div class="modal-header border-0 pb-0"><h5 class="modal-title"><i class="fa-solid fa-feather me-2"></i>Nova publicação relacional</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
      <div class="modal-body pt-2">
        <form method="post" action="/feed/post" enctype="multipart/form-data" class="d-grid gap-2">
          <?= csrf_field() ?>
          <textarea class="form-control" name="content" maxlength="2000" rows="4" placeholder="Escrever livremente..."></textarea>
          <div class="row g-2">
            <div class="col-6"><select class="form-select form-select-sm" name="post_mood"><option value="">Mood do momento</option><option value="disponivel_para_conversar">Disponível para conversar</option><option value="energia_leve">Energia leve</option><option value="romantico_hoje">Romântico hoje</option><option value="mente_aberta">Mente aberta</option><option value="quero_algo_serio">Quero algo sério</option><option value="so_a_observar">Só a observar</option></select></div>
            <div class="col-6"><select class="form-select form-select-sm" name="relational_phase"><option value="">Fase relacional</option><option value="recomeco">Recomeço</option><option value="amizade">Amizade</option><option value="namoro">Namoro</option><option value="casamento">Casamento</option><option value="cura_emocional">Cura emocional</option><option value="explorar_possibilidades">Explorar possibilidades</option></select></div>
          </div>
          <div class="rd-prompt-box">
            <label class="form-label small">Responder um prompt (opcional)</label>
            <select class="form-select form-select-sm" name="prompt_id"><option value="0">Sem prompt</option><?php foreach (($prompts ?? []) as $prompt): ?><option value="<?= (int) ($prompt['id'] ?? 0) ?>"><?= e((string) ($prompt['prompt_text'] ?? '')) ?></option><?php endforeach; ?></select>
            <textarea class="form-control form-control-sm mt-1" name="prompt_answer_text" rows="2" maxlength="2000" placeholder="A tua resposta ao prompt..."></textarea>
          </div>
          <div class="rd-poll-box">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="has_poll" value="1"> <span class="form-check-label">Adicionar enquete romântica</span></label>
            <input class="form-control form-control-sm mt-1" name="poll_question" maxlength="255" placeholder="Pergunta da enquete">
            <div class="row g-1 mt-1"><div class="col-6"><input class="form-control form-control-sm" name="poll_option_1" placeholder="Opção 1"></div><div class="col-6"><input class="form-control form-control-sm" name="poll_option_2" placeholder="Opção 2"></div><div class="col-6"><input class="form-control form-control-sm" name="poll_option_3" placeholder="Opção 3 (opcional)"></div><div class="col-6"><input class="form-control form-control-sm" name="poll_option_4" placeholder="Opção 4 (opcional)"></div></div>
          </div>
          <input class="form-control form-control-sm" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
          <small class="text-muted d-block">Até 4 imagens por publicação.</small>
          <div class="d-flex justify-content-end"><button class="btn btn-rd-primary">Publicar</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="rd-feed-shell">
<?php if (!empty($feed)): foreach (($feed ?? []) as $post): ?>
  <?php $postId = (int) ($post['id'] ?? 0); $authorName = (string) ($post['author_name'] ?? ('Utilizador #' . (int) $post['user_id'])); $authorInitial = strtoupper(substr(trim($authorName), 0, 1)); ?>
  <article class="rd-card rd-feed-post <?= $selectedPostId === $postId ? 'rd-post-highlight' : '' ?>" id="post-<?= $postId ?>">
    <div class="card-body">
      <header class="d-flex justify-content-between gap-2 mb-2"><div class="rd-feed-post__author"><div class="rd-feed-post__avatar"><?= e($authorInitial !== '' ? $authorInitial : 'U') ?></div><div><a class="text-decoration-none fw-semibold" href="/member/<?= (int) ($post['user_id'] ?? 0) ?>"><?= e($authorName) ?></a><div class="rd-feed-post__meta"><?php if ((int) ($post['author_online'] ?? 0) === 1): ?><span class="badge text-bg-success">online</span><?php endif; ?><?php if ((int) ($post['author_verified'] ?? 0) === 1): ?><span class="badge text-bg-primary">verificado</span><?php endif; ?><?php if (!empty($post['author_trust_flags']['premium'])): ?><span class="badge text-bg-warning">premium</span><?php endif; ?><?php if (!empty($post['author_availability']['availability_type'])): ?><span class="badge text-bg-info"><?= e(str_replace('_', ' ', (string) $post['author_availability']['availability_type'])) ?></span><?php endif; ?></div></div></div><small class="text-muted text-nowrap"><?= e((string) $post['created_at']) ?></small></header>

      <div class="rd-feed-relational-signals mb-2"><?php if (!empty($post['post_mood'])): ?><span class="badge rounded-pill text-bg-light">Mood: <?= e(str_replace('_', ' ', (string) $post['post_mood'])) ?></span><?php endif; ?><?php if (!empty($post['relational_phase'])): ?><span class="badge rounded-pill text-bg-light">Fase: <?= e(str_replace('_', ' ', (string) $post['relational_phase'])) ?></span><?php endif; ?><span class="badge rounded-pill text-bg-light">Compatibilidade <?= (int) round((float) ($post['compatibility_score'] ?? 0)) ?>%</span><?php if ((int) ($post['same_intention'] ?? 0) === 1): ?><span class="badge rounded-pill text-bg-success">Mesma intenção</span><?php endif; ?><?php if ((int) ($post['same_pace'] ?? 0) === 1): ?><span class="badge rounded-pill text-bg-secondary">Ritmo semelhante</span><?php endif; ?></div>

      <?php if (!empty($post['prompt_answer'])): ?><div class="rd-prompt-answer mb-2"><small class="text-muted d-block">Prompt: <?= e((string) ($post['prompt_answer']['prompt_snapshot'] ?? '')) ?></small><p class="mb-0"><?= e((string) ($post['prompt_answer']['answer_text'] ?? '')) ?></p></div><?php endif; ?>
      <?php if (!empty($post['content'])): ?><p class="rd-feed-post__body mb-2"><?= e((string) ($post['content'] ?? '')) ?></p><?php endif; ?>

      <?php if (!empty($post['poll'])): ?>
        <section class="rd-poll-card mb-2" data-poll-id="<?= (int) ($post['poll']['id'] ?? 0) ?>">
          <strong class="d-block mb-1"><?= e((string) ($post['poll']['question'] ?? '')) ?></strong>
          <div class="d-grid gap-1">
            <?php foreach (($post['poll']['options'] ?? []) as $pollOption): ?>
              <?php $isVoted = (int) ($post['poll']['viewer_option_id'] ?? 0) === (int) ($pollOption['id'] ?? 0); ?>
              <button type="button" class="btn btn-sm <?= $isVoted ? 'btn-primary' : 'btn-outline-primary' ?> text-start" data-feed-poll-vote data-poll-id="<?= (int) ($post['poll']['id'] ?? 0) ?>" data-option-id="<?= (int) ($pollOption['id'] ?? 0) ?>"><?= e((string) ($pollOption['option_text'] ?? '')) ?> <span class="float-end"><?= (int) ($pollOption['percentage'] ?? 0) ?>%</span></button>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($post['images'])): ?><div class="row g-2 mb-2"><?php foreach (($post['images'] ?? []) as $imageIndex => $image): $fullImagePath = trim((string) ($image['image_path'] ?? '')); if ($fullImagePath === '') { continue; } $thumbPath = trim((string) (($image['thumbnail_path'] ?? '') !== '' ? $image['thumbnail_path'] : $fullImagePath)); ?><div class="col-6 col-md-3"><a href="<?= e(url($fullImagePath)) ?>" data-lightbox-group="post-<?= $postId ?>" data-lightbox-index="<?= (int) $imageIndex ?>" class="text-decoration-none"><img src="<?= e(url($thumbPath)) ?>" class="img-fluid rounded border" alt="imagem do post"></a></div><?php endforeach; ?></div><?php endif; ?>

      <div class="rd-feed-post__stats small text-muted mb-2">❤️ <span data-like-count data-post-like-count="<?= $postId ?>"><?= (int) ($post['likes_count'] ?? 0) ?></span><span class="mx-1">·</span>💬 <?= (int) ($post['comments_count'] ?? 0) ?><span class="mx-1">·</span>✨ <?= array_sum((array) ($post['reactions'] ?? [])) ?><span class="mx-1">·</span>🕊️ <?= (int) ($post['private_interest_count'] ?? 0) ?></div>

      <div class="rd-feed-reactions mb-2" data-post-id="<?= $postId ?>">
        <?php foreach (($reaction_types ?? []) as $reactionType): ?>
          <?php $isActiveReaction = (string) ($post['viewer_reaction'] ?? '') === (string) $reactionType; ?>
          <button type="button" class="btn btn-sm <?= $isActiveReaction ? 'btn-primary' : 'btn-outline-secondary' ?>" data-feed-reaction data-post-id="<?= $postId ?>" data-reaction-type="<?= e((string) $reactionType) ?>"><?= e(str_replace('_', ' ', (string) $reactionType)) ?> <span class="badge text-bg-light"><?= (int) (($post['reactions'][$reactionType] ?? 0)) ?></span></button>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($post['owner_interaction_summary']) && (int) ($post['user_id'] ?? 0) === $viewerId): ?><div class="rd-owner-insights mb-2 small">👥 <?= (int) ($post['owner_interaction_summary']['compatible_people'] ?? 0) ?> pessoas compatíveis interagiram · 🎯 <?= (int) ($post['owner_interaction_summary']['same_intention_people'] ?? 0) ?> com a tua intenção · 📍 <?= (int) ($post['owner_interaction_summary']['nearby_people'] ?? 0) ?> próximas de ti.</div><?php endif; ?>

      <div class="small d-none rd-feed-inline-feedback mb-2" data-feed-feedback data-post-id="<?= $postId ?>" aria-live="polite"></div>

      <div class="d-flex flex-wrap gap-2 align-items-start">
        <form method="post" action="/feed/like" class="m-0" data-feed-like-form><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><button class="btn btn-sm <?= (int) ($post['liked_by_viewer'] ?? 0) === 1 ? 'btn-danger' : 'btn-outline-danger' ?>" data-feed-like-button data-liked="<?= (int) ($post['liked_by_viewer'] ?? 0) ?>"><i class="fa-solid fa-heart me-1"></i><?= (int) ($post['liked_by_viewer'] ?? 0) === 1 ? 'Gostado' : 'Gostar' ?></button></form>
        <details class="m-0"><summary class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-comment me-1"></i>Comentar</summary><form method="post" action="/feed/comment" class="mt-2 d-flex gap-2" data-feed-comment-form data-comment-kind="comment" data-post-id="<?= $postId ?>" data-parent-id="0"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><input type="hidden" name="parent_comment_id" value=""><input class="form-control form-control-sm" name="comment" maxlength="600" placeholder="Escreva um comentário..." required><button class="btn btn-sm btn-rd-primary">Enviar</button></form></details>
        <details class="m-0"><summary class="btn btn-sm btn-outline-primary">Interesse privado</summary><form class="mt-2 d-grid gap-1" data-feed-private-interest-form><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><select class="form-select form-select-sm" name="interest_type"><?php foreach (($interest_types ?? []) as $interestType): ?><option value="<?= e((string) $interestType) ?>"><?= e(str_replace('_', ' ', (string) $interestType)) ?></option><?php endforeach; ?></select><input class="form-control form-control-sm" name="message_optional" maxlength="240" placeholder="Mensagem opcional"><button class="btn btn-sm btn-outline-primary">Enviar</button></form></details>
        <?php if ((int) ($post['user_id'] ?? 0) === $viewerId): ?><form method="post" action="/feed/delete"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><button class="btn btn-sm btn-outline-dark">Apagar</button></form><?php endif; ?>
      </div>

      <?php if (!empty($post['comments'])): ?><div class="rd-feed-comment-box small mt-2 d-grid gap-2 rd-comment-thread"><?php foreach (($post['comments'] ?? []) as $comment): $commentId = (int) ($comment['id'] ?? 0); $isTargetComment = $selectedCommentId === $commentId; ?><div id="comment-<?= $commentId ?>" class="rd-feed-comment-item <?= $isTargetComment ? 'rd-comment-highlight' : '' ?>"><div><a href="/member/<?= (int) ($comment['user_id'] ?? 0) ?>" class="fw-semibold text-decoration-none"><?= e((string) ($comment['author_name'] ?? 'Utilizador')) ?></a>: <?= e((string) ($comment['comment_text'] ?? '')) ?></div></div><?php endforeach; ?></div><?php endif; ?>
    </div>
  </article>
<?php endforeach; else: $title='Feed vazio'; $description='Ainda não há publicações ativas para mostrar.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
</div>

<?php if (($pg['total_pages'] ?? 1) > 1): ?><nav class="mt-3"><ul class="pagination pagination-sm"><?php for ($p = 1; $p <= (int) $pg['total_pages']; $p++): ?><li class="page-item <?= $p === (int) $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="/feed?page=<?= $p ?>&tab=<?= e($selectedTab) ?>"><?= $p ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
