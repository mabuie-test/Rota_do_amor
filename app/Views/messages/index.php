<h3 class="mb-3"><i class="fa-solid fa-comments me-2"></i>Mensagens</h3>
<div class="row g-3">
  <div class="col-lg-4"><div class="rd-card"><div class="card-body"><h6>Conversas</h6>
    <form method="get" class="mb-2"><input class="form-control form-control-sm" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Pesquisar conversa"></form>
    <?php if (!empty($conversations)): foreach ($conversations as $conversation): ?>
      <a href="/messages/<?= (int) $conversation['id'] ?>" class="d-block border rounded p-2 mb-2 text-decoration-none">
        <div class="d-flex justify-content-between align-items-center">
          <strong><?= e((string) ($conversation['other_user_name'] ?? 'Utilizador')) ?></strong>
          <?php if ((int) ($conversation['unread_count'] ?? 0) > 0): ?><span class="badge bg-danger"><?= (int) $conversation['unread_count'] ?></span><?php endif; ?>
        </div>
        <div class="small text-muted">
          <?php if (($conversation['last_message_type'] ?? 'text') === 'image'): ?>📷 imagem<?php else: ?><?= e((string) ($conversation['last_message'] ?? 'Sem mensagens')) ?><?php endif; ?>
        </div>
        <div class="small text-muted">
          <?= e((string) ($conversation['last_message_at'] ?? $conversation['updated_at'])) ?>
          · <?= (int) ($conversation['other_online_status'] ?? 0) === 1 ? 'online' : 'offline' ?>
          <?php if (!empty($conversation['other_profile_photo'])): ?>· foto ok<?php endif; ?>
        </div>
      </a>
    <?php endforeach; else: $title='Sem conversas'; $description='Quando houver match, as conversas aparecem aqui.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
  </div></div></div>
  <div class="col-lg-8"><div class="rd-card"><div class="card-body"><h6>Conversa ativa</h6><p class="text-muted">Selecione uma conversa para ver detalhes, estado online e histórico paginado.</p></div></div></div>
</div>
