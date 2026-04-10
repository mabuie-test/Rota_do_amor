<?php $pg = $pagination ?? ['page' => 1, 'total_pages' => 1]; $viewerId = (int) ($viewer_id ?? 0); ?>
<h3 class="mb-3"><i class="fa-solid fa-newspaper me-2"></i>Feed Social</h3>
<form method="post" action="/feed/post" class="rd-card mb-3" enctype="multipart/form-data"><?= csrf_field() ?><div class="card-body"><textarea class="form-control mb-2" name="content" maxlength="2000" placeholder="Partilhe algo com a comunidade..."></textarea><input class="form-control form-control-sm mb-2" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="text-muted d-block mb-2">Até 4 imagens por post.</small><button class="btn btn-rd-primary">Publicar</button></div></form>
<?php if (!empty($feed)): foreach (($feed ?? []) as $post): ?>
  <div class="rd-card mb-2"><div class="card-body">
    <div class="d-flex justify-content-between">
      <div>
        <strong><?= e((string) ($post['author_name'] ?? ('Utilizador #' . (int) $post['user_id']))) ?></strong>
        <?php if ((int) ($post['author_online'] ?? 0) === 1): ?><span class="badge bg-success">online</span><?php endif; ?>
        <?php if ((int) ($post['author_verified'] ?? 0) === 1): ?><span class="badge bg-primary">verificado</span><?php endif; ?>
      </div>
      <small class="text-muted"><?= e((string) $post['created_at']) ?></small>
    </div>
    <?php if (!empty($post['content'])): ?><p class="my-2"><?= e((string) ($post['content'] ?? '')) ?></p><?php endif; ?>

    <?php if (!empty($post['images'])): ?>
      <div class="row g-2 mb-2">
        <?php foreach (($post['images'] ?? []) as $image): ?>
          <div class="col-md-3 col-6">
            <a href="/<?= e((string) ($image['image_path'] ?? '')) ?>" target="_blank" class="text-decoration-none">
              <img src="/<?= e((string) (($image['thumbnail_path'] ?? '') !== '' ? $image['thumbnail_path'] : ($image['image_path'] ?? ''))) ?>" class="img-fluid rounded border" alt="imagem do post">
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="small text-muted mb-2">❤️ <?= (int) ($post['likes_count'] ?? 0) ?> · 💬 <?= (int) ($post['comments_count'] ?? 0) ?> · 📎 <?= (int) ($post['images_count'] ?? 0) ?> · visibilidade <?= e((string) ($post['status'] ?? 'active')) ?></div>
    <?php if (!empty($post['recent_comments'])): ?>
      <div class="border rounded p-2 bg-light-subtle small mb-2">
        <?php foreach (($post['recent_comments'] ?? []) as $comment): ?>
          <div><strong><?= e((string) ($comment['author_name'] ?? 'Utilizador')) ?>:</strong> <?= e((string) ($comment['comment_text'] ?? '')) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 align-items-start">
      <form method="post" action="/feed/like" class="m-0">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
        <button class="btn btn-sm <?= (int) ($post['liked_by_viewer'] ?? 0) === 1 ? 'btn-danger' : 'btn-outline-danger' ?>" title="Gostar">
          <i class="fa-solid fa-heart me-1"></i><?= (int) ($post['liked_by_viewer'] ?? 0) === 1 ? 'Gostado' : 'Gostar' ?>
        </button>
      </form>
      <details class="m-0">
        <summary class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-comment me-1"></i>Comentar</summary>
        <form method="post" action="/feed/comment" class="mt-2 d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
          <input class="form-control form-control-sm" name="comment" maxlength="600" placeholder="Escreva um comentário..." required>
          <button class="btn btn-sm btn-rd-primary">Enviar</button>
        </form>
      </details>
      <details class="m-0">
        <summary class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-flag me-1"></i>Denunciar</summary>
        <form method="post" action="/report" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="report_type" value="post">
          <input type="hidden" name="target_post_id" value="<?= (int) $post['id'] ?>">
          <div class="d-flex gap-2">
            <select class="form-select form-select-sm" name="reason" required>
              <option value="">Motivo...</option>
              <option value="spam">Spam</option>
              <option value="abuse">Abuso</option>
              <option value="adult_content">Conteúdo impróprio</option>
              <option value="fake_profile">Perfil falso</option>
            </select>
            <button class="btn btn-sm btn-warning">Enviar</button>
          </div>
        </form>
      </details>
      <?php if ((int) ($post['user_id'] ?? 0) === $viewerId): ?>
        <form method="post" action="/feed/delete"><?= csrf_field() ?><input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>"><button class="btn btn-sm btn-outline-dark">Apagar</button></form>
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
