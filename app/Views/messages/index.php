<h3 class="mb-3"><i class="fa-solid fa-comments me-2"></i>Mensagens</h3>
<?php
$activeConversationId = (int) ($active_conversation_id ?? 0);
$viewerId = (int) ($viewer_id ?? 0);
$context = $context ?? [];
$otherId = (int) ($context['other_user_id'] ?? 0);
$pg = $pagination ?? ['page' => 1, 'has_more' => false];
$otherStatusLabel = (int) ($context['other_online_status'] ?? 0) === 1 ? 'Online agora' : 'Offline';
$otherIntentionRaw = (string) ($context['other_current_intention'] ?? '');
$otherPaceRaw = (string) ($context['other_relational_pace'] ?? '');
$otherIntention = $otherIntentionRaw !== '' ? ucwords(str_replace('_', ' ', $otherIntentionRaw)) : '';
$otherPace = $otherPaceRaw !== '' ? ucwords(str_replace('_', ' ', $otherPaceRaw)) : '';
?>
<div class="row g-3">
  <div class="col-lg-4"><div class="rd-card"><div class="card-body"><h6>Conversas</h6>
    <form method="get" class="mb-2"><input class="form-control form-control-sm" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Pesquisar conversa"></form>
    <?php if (!empty($conversations)): foreach ($conversations as $conversation): ?>
      <?php $isActive = (int) $conversation['id'] === $activeConversationId; ?>
      <a href="/messages?conversation=<?= (int) $conversation['id'] ?>" class="d-block border rounded p-2 mb-2 text-decoration-none <?= $isActive ? 'border-primary bg-primary-subtle shadow-sm' : 'border-light-subtle' ?>">
        <div class="d-flex justify-content-between align-items-center">
          <strong><?= e((string) ($conversation['other_user_name'] ?? 'Utilizador')) ?></strong>
          <?php if ((int) ($conversation['unread_count'] ?? 0) > 0): ?><span class="badge bg-danger"><?= (int) $conversation['unread_count'] ?></span><?php endif; ?>
        </div>
        <div class="small text-muted">
          <?php if (($conversation['last_message_type'] ?? 'text') === 'image'): ?>📷 Imagem partilhada<?php else: ?><?= e((string) ($conversation['last_message'] ?? 'Sem mensagens ainda')) ?><?php endif; ?>
        </div>
        <div class="small text-muted">
          <?= e((string) ($conversation['last_message_at'] ?? $conversation['updated_at'])) ?> ·
          <?= (int) ($conversation['other_online_status'] ?? 0) === 1 ? 'Online' : 'Offline' ?>
        </div>
      </a>
    <?php endforeach; else: $title='Sem conversas'; $description='Quando houver match, as conversas aparecem aqui.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
  </div></div></div>
  <div class="col-lg-8">
    <div class="rd-card"><div class="card-body">
      <h6 class="mb-1">Conversa ativa</h6>
      <?php if (!empty($context)): ?>
        <div class="small text-muted mb-2 p-2 border rounded bg-light-subtle">
          <strong><?= e((string) ($context['other_user_name'] ?? 'Utilizador')) ?></strong>
          · <?= $otherStatusLabel ?>
          <?php if ((int) ($context['other_is_verified'] ?? 0) === 1): ?>· Conta verificada<?php endif; ?>
          <?php if (!empty($context['other_last_activity_at'])): ?>· Última atividade: <?= e((string) $context['other_last_activity_at']) ?><?php endif; ?>
          <?php if ($otherIntention !== ''): ?><span class="rd-heart-chip ms-2"><i class="fa-solid fa-heart-pulse"></i><?= e($otherIntention) ?></span><?php endif; ?>
          <?php if ($otherPace !== ''): ?><span class="rd-heart-chip ms-1"><i class="fa-solid fa-wave-square"></i><?= e($otherPace) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($pg['has_more'])): ?><a class="btn btn-sm btn-outline-secondary mb-2" href="/messages?conversation=<?= $activeConversationId ?>&page=<?= (int) ($pg['page'] + 1) ?>">Carregar mensagens anteriores (página <?= (int) ($pg['page'] + 1) ?>)</a><?php endif; ?>
        <div class="border rounded p-3 mb-3" style="max-height:420px; overflow:auto;">
          <?php if (!empty($messages)): foreach ($messages as $msg): ?>
            <?php $isMine = (int) ($msg['sender_id'] ?? 0) === $viewerId; ?>
            <div class="mb-2 <?= $isMine ? 'text-end' : '' ?>">
              <div class="small fw-semibold mb-1"><?= $isMine ? 'Tu' : e((string) ($context['other_user_name'] ?? 'Utilizador')) ?></div>
              <div class="d-inline-block p-2 rounded <?= $isMine ? 'bg-primary text-white' : 'bg-light border' ?>" style="max-width: 88%;">
                <?php if (($msg['message_type'] ?? 'text') === 'image'): ?><div class="small mb-1 <?= $isMine ? 'text-white-50' : 'text-muted' ?>">📷 Imagem</div><?php endif; ?>
                <?php if (($msg['message_type'] ?? 'text') !== 'image' || trim((string) ($msg['message_text'] ?? '')) !== '[imagem]'): ?>
                  <div><?= e((string) ($msg['message_text'] ?? '')) ?></div>
                <?php endif; ?>
                <?php if (!empty($msg['attachments'])): ?>
                  <div class="mt-2">
                    <?php foreach ($msg['attachments'] as $attachment): ?>
                      <a href="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" target="_blank" class="d-inline-block me-1 mb-1">
                        <img src="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" style="max-width: 220px" class="img-fluid rounded border" alt="imagem anexada na conversa">
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div><small class="text-muted"><?= e((string) ($msg['created_at'] ?? '')) ?></small></div>
            </div>
          <?php endforeach; else: $title='Sem mensagens nesta conversa'; $description='Envia uma mensagem de boas-vindas para iniciar o histórico.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
        </div>
        <form method="post" action="/messages/send" class="d-flex gap-2" enctype="multipart/form-data"><?= csrf_field() ?>
          <input type="hidden" name="receiver_id" value="<?= $otherId ?>">
          <input type="hidden" name="message_type" value="text">
          <input class="form-control" name="message_text" maxlength="2000" placeholder="Escreve a tua mensagem (opcional quando enviar imagem)">
          <input class="form-control form-control-sm" style="max-width:220px" type="file" name="image" accept="image/jpeg,image/png,image/webp">
          <button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
      <?php else: ?>
        <p class="text-muted mb-0">Selecione uma conversa à esquerda para ver o histórico paginado e continuar o chat.</p>
      <?php endif; ?>
    </div></div>
  </div>
</div>
