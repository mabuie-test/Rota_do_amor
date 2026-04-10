<h3 class="mb-3"><i class="fa-solid fa-comments me-2"></i>Mensagens</h3>
<?php
$activeConversationId = (int) ($active_conversation_id ?? 0);
$context = $context ?? [];
$otherId = (int) ($context['other_user_id'] ?? 0);
$pg = $pagination ?? ['page' => 1, 'has_more' => false];
?>
<div class="row g-3">
  <div class="col-lg-4"><div class="rd-card"><div class="card-body"><h6>Conversas</h6>
    <form method="get" class="mb-2"><input class="form-control form-control-sm" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Pesquisar conversa"></form>
    <?php if (!empty($conversations)): foreach ($conversations as $conversation): ?>
      <?php $isActive = (int) $conversation['id'] === $activeConversationId; ?>
      <a href="/messages?conversation=<?= (int) $conversation['id'] ?>" class="d-block border rounded p-2 mb-2 text-decoration-none <?= $isActive ? 'border-primary bg-light-subtle' : '' ?>">
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
  <div class="col-lg-8">
    <div class="rd-card"><div class="card-body">
      <h6 class="mb-1">Conversa ativa</h6>
      <?php if (!empty($context)): ?>
        <div class="small text-muted mb-2">
          <strong><?= e((string) ($context['other_user_name'] ?? 'Utilizador')) ?></strong>
          · <?= (int) ($context['other_online_status'] ?? 0) === 1 ? 'online' : 'offline' ?>
          <?php if (!empty($context['other_last_activity_at'])): ?>· última actividade <?= e((string) $context['other_last_activity_at']) ?><?php endif; ?>
          <?php if ((int) ($context['other_is_verified'] ?? 0) === 1): ?>· verificado<?php endif; ?>
        </div>
        <?php if (!empty($pg['has_more'])): ?><a class="btn btn-sm btn-outline-secondary mb-2" href="/messages?conversation=<?= $activeConversationId ?>&page=<?= (int) ($pg['page'] + 1) ?>">Carregar histórico anterior</a><?php endif; ?>
        <div class="border rounded p-3 mb-3" style="max-height:420px; overflow:auto;">
          <?php if (!empty($messages)): foreach ($messages as $msg): ?>
            <div class="mb-2">
              <span class="rd-badge badge-active">#<?= (int) $msg['sender_id'] ?></span>
              <?php if (($msg['message_type'] ?? 'text') === 'image'): ?><span class="badge bg-secondary">imagem</span><?php endif; ?>
              <?php if (($msg['message_type'] ?? 'text') !== 'image' || trim((string) ($msg['message_text'] ?? '')) !== '[imagem]'): ?>
                <?= e((string) ($msg['message_text'] ?? '')) ?>
              <?php endif; ?>
              <small class="text-muted">· <?= e((string) ($msg['created_at'] ?? '')) ?></small>
              <?php if (!empty($msg['attachments'])): ?>
                <div class="mt-1">
                  <?php foreach ($msg['attachments'] as $attachment): ?>
                    <a href="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" target="_blank">
                      <img src="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" style="max-width: 180px" class="img-fluid rounded border" alt="anexo da mensagem">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; else: $title='Sem mensagens'; $description='Envie a primeira mensagem para iniciar a conversa.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
        </div>
        <form method="post" action="/messages/send" class="d-flex gap-2" enctype="multipart/form-data"><?= csrf_field() ?>
          <input type="hidden" name="receiver_id" value="<?= $otherId ?>">
          <input type="hidden" name="message_type" value="text">
          <input class="form-control" name="message_text" maxlength="2000" placeholder="Digite sua mensagem (opcional com imagem)">
          <input class="form-control form-control-sm" style="max-width:220px" type="file" name="image" accept="image/jpeg,image/png,image/webp">
          <button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
      <?php else: ?>
        <p class="text-muted mb-0">Selecione uma conversa para ver histórico paginado e enviar mensagens com texto/imagem.</p>
      <?php endif; ?>
    </div></div>
  </div>
</div>
