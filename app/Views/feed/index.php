<?php $pg = $pagination ?? ['page' => 1, 'total_pages' => 1]; $viewerId = (int) ($viewer_id ?? 0); $selectedPostId = (int) ($selected_post_id ?? 0); ?>
<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
  <h3 class="mb-0"><i class="fa-solid fa-sparkles me-2"></i>Feed Social</h3>
  <button class="btn btn-rd-primary" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fa-solid fa-pen-to-square me-1"></i>Criar publicação</button>
</div>

<div class="modal fade" id="createPostModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rd-card">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><i class="fa-solid fa-feather me-2"></i>Nova publicação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="/feed/post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <textarea class="form-control mb-2" name="content" maxlength="2000" placeholder="Partilhe algo com a comunidade..."></textarea>
          <input class="form-control form-control-sm mb-2" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
          <small class="text-muted d-block mb-2">Até 4 imagens por post.</small>
          <button class="btn btn-rd-primary">Publicar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($feed)): foreach (($feed ?? []) as $post): ?>
  <?php $postId = (int) ($post['id'] ?? 0); ?>
  <div class="rd-card mb-2 <?= $selectedPostId === $postId ? 'rd-post-highlight' : '' ?>" id="post-<?= $postId ?>"><div class="card-body">
    <div class="d-flex justify-content-between gap-2">
      <div>
        <a class="text-decoration-none" href="/discover/profile/<?= (int) ($post['user_id'] ?? 0) ?>"><strong><?= e((string) ($post['author_name'] ?? ('Utilizador #' . (int) $post['user_id']))) ?></strong></a>
        <?php if ((int) ($post['author_online'] ?? 0) === 1): ?><span class="badge bg-success">online</span><?php endif; ?>
        <?php if ((int) ($post['author_verified'] ?? 0) === 1): ?><span class="badge bg-primary">verificado</span><?php endif; ?>
      </div>
      <small class="text-muted"><?= e((string) $post['created_at']) ?></small>
    </div>
    <?php if (!empty($post['content'])): ?><p class="my-2"><?= e((string) ($post['content'] ?? '')) ?></p><?php endif; ?>

    <?php if (!empty($post['images'])): ?>
      <div class="row g-2 mb-2">
        <?php foreach (($post['images'] ?? []) as $imageIndex => $image): ?>
          <div class="col-md-3 col-6">
            <a href="<?= e(url((string) ($image['image_path'] ?? ''))) ?>" data-lightbox-group="post-<?= $postId ?>" data-lightbox-index="<?= (int) $imageIndex ?>" class="text-decoration-none">
              <img src="<?= e(url((string) (($image['thumbnail_path'] ?? '') !== '' ? $image['thumbnail_path'] : ($image['image_path'] ?? '')))) ?>" class="img-fluid rounded border" alt="imagem do post">
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="small text-muted mb-2">❤️ <?= (int) ($post['likes_count'] ?? 0) ?> · 💬 <?= (int) ($post['comments_count'] ?? 0) ?> · 📎 <?= (int) ($post['images_count'] ?? 0) ?></div>

    <?php if (!empty($post['comments'])): ?>
      <div class="border rounded p-2 bg-light-subtle small mb-2 d-flex flex-column gap-2">
        <?php foreach (($post['comments'] ?? []) as $comment): ?>
          <div>
            <div><a href="/discover/profile/<?= (int) ($comment['user_id'] ?? 0) ?>" class="fw-semibold text-decoration-none"><?= e((string) ($comment['author_name'] ?? 'Utilizador')) ?></a>: <?= e((string) ($comment['comment_text'] ?? '')) ?></div>
            <div class="small text-muted d-flex gap-2">
              <span><?= (int) ($comment['reply_count'] ?? 0) ?> respostas</span>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-reply-toggle data-post-id="<?= $postId ?>" data-parent-id="<?= (int) ($comment['id'] ?? 0) ?>">Responder</button>
            </div>
            <?php if (!empty($comment['replies'])): ?>
              <div class="ps-3 mt-1 border-start">
                <?php foreach (($comment['replies'] ?? []) as $reply): ?>
                  <div><a href="/discover/profile/<?= (int) ($reply['user_id'] ?? 0) ?>" class="fw-semibold text-decoration-none"><?= e((string) ($reply['author_name'] ?? 'Utilizador')) ?></a>: <?= e((string) ($reply['comment_text'] ?? '')) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <form method="post" action="/feed/comment" class="mt-1 d-none" id="reply-form-<?= $postId ?>-<?= (int) ($comment['id'] ?? 0) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="post_id" value="<?= $postId ?>">
              <input type="hidden" name="parent_comment_id" value="<?= (int) ($comment['id'] ?? 0) ?>">
              <div class="d-flex gap-2">
                <input class="form-control form-control-sm" name="comment" maxlength="600" placeholder="Responder comentário..." required>
                <button class="btn btn-sm btn-rd-primary">Responder</button>
              </div>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 align-items-start">
      <form method="post" action="/feed/like" class="m-0">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= $postId ?>">
        <button class="btn btn-sm <?= (int) ($post['liked_by_viewer'] ?? 0) === 1 ? 'btn-danger' : 'btn-outline-danger' ?>" title="Gostar">
          <i class="fa-solid fa-heart me-1"></i><?= (int) ($post['liked_by_viewer'] ?? 0) === 1 ? 'Gostado' : 'Gostar' ?>
        </button>
      </form>
      <details class="m-0">
        <summary class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-comment me-1"></i>Comentar</summary>
        <form method="post" action="/feed/comment" class="mt-2 d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="post_id" value="<?= $postId ?>">
          <input type="hidden" name="parent_comment_id" value="">
          <input class="form-control form-control-sm" name="comment" maxlength="600" placeholder="Escreva um comentário..." required>
          <button class="btn btn-sm btn-rd-primary">Enviar</button>
        </form>
      </details>
      <a class="btn btn-sm btn-outline-secondary" href="/discover/profile/<?= (int) ($post['user_id'] ?? 0) ?>">Perfil</a>
      <?php if ((int) ($post['user_id'] ?? 0) === $viewerId): ?>
        <form method="post" action="/feed/delete"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $postId ?>"><button class="btn btn-sm btn-outline-dark">Apagar</button></form>
      <?php endif; ?>
    </div>
  </div></div>
<?php endforeach; else: $title='Feed vazio'; $description='Ainda não há publicações ativas para mostrar.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>

<?php if (($pg['total_pages'] ?? 1) > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($p = 1; $p <= (int) $pg['total_pages']; $p++): ?>
    <li class="page-item <?= $p === (int) $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="/feed?page=<?= $p ?>"><?= $p ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>
