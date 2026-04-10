<?php $pg = $pagination ?? ['page' => 1, 'total_pages' => 1]; ?>
<h3 class="mb-3"><i class="fa-solid fa-newspaper me-2"></i>Feed Social</h3>
<form method="post" action="/feed/post" class="rd-card mb-3"><?= csrf_field() ?><div class="card-body"><textarea class="form-control mb-2" name="content" maxlength="2000" placeholder="Partilhe algo com a comunidade..."></textarea><button class="btn btn-rd-primary">Publicar</button></div></form>
<?php foreach (($feed ?? []) as $post): ?>
  <div class="rd-card mb-2"><div class="card-body">
    <div class="d-flex justify-content-between">
      <div>
        <strong><?= e((string) ($post['author_name'] ?? ('Utilizador #' . (int) $post['user_id']))) ?></strong>
        <?php if ((int) ($post['author_online'] ?? 0) === 1): ?><span class="badge bg-success">online</span><?php endif; ?>
      </div>
      <small class="text-muted"><?= e((string) $post['created_at']) ?></small>
    </div>
    <p class="my-2"><?= e((string) ($post['content'] ?? '')) ?></p>
    <?php if (!empty($post['first_image_path'])): ?><div class="small text-muted mb-2">📷 Media anexada (1ª imagem): <?= e((string) $post['first_image_path']) ?></div><?php endif; ?>
    <div class="small text-muted mb-2">❤️ <?= (int) ($post['likes_count'] ?? 0) ?> · 💬 <?= (int) ($post['comments_count'] ?? 0) ?> · 📎 <?= (int) ($post['images_count'] ?? 0) ?> · visibilidade <?= e((string) ($post['status'] ?? 'active')) ?></div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-heart"></i></button><button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-comment"></i></button><button class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-flag"></i></button></div>
  </div></div>
<?php endforeach; ?>

<?php if (($pg['total_pages'] ?? 1) > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
  <?php for ($p = 1; $p <= (int) $pg['total_pages']; $p++): ?>
    <li class="page-item <?= $p === (int) $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="/feed?page=<?= $p ?>"><?= $p ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>
