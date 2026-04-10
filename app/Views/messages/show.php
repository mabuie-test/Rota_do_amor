<?php $context = $context ?? []; $viewerId = (int) ($viewer_id ?? 0); $otherId = (int) ($context['other_user_id'] ?? 0); $pg = $pagination ?? ['page' => 1, 'has_more' => false]; ?>
<h3 class="mb-3">Conversa com <?= e((string) ($context['other_user_name'] ?? 'Utilizador')) ?></h3>
<div class="small text-muted mb-2 p-2 border rounded bg-light-subtle">
  <?= (int) ($context['other_online_status'] ?? 0) === 1 ? 'online' : 'offline' ?>
  <?php if (!empty($context['other_last_activity_at'])): ?>· última actividade <?= e((string) $context['other_last_activity_at']) ?><?php endif; ?>
  <?php if ((int) ($context['other_is_verified'] ?? 0) === 1): ?>· verificado<?php endif; ?>
  <?php if (!empty($context['other_user_status'])): ?>· estado <?= e((string) $context['other_user_status']) ?><?php endif; ?>
  <?php if (!empty($context['other_profile_photo'])): ?>· perfil com foto<?php endif; ?>
</div>
<a class="btn btn-sm btn-outline-secondary mb-2" href="/messages?conversation=<?= (int) ($context['id'] ?? 0) ?>">Abrir vista completa da inbox</a>
<?php if (!empty($pg['has_more'])): ?><a class="btn btn-sm btn-outline-secondary mb-2" href="?page=<?= (int) ($pg['page'] + 1) ?>">Carregar mensagens anteriores</a><?php endif; ?>
<div class="rd-card mb-3"><div class="card-body" style="max-height:420px; overflow:auto;">
<?php if (!empty($messages)): foreach (($messages ?? []) as $msg): ?>
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
          <a href="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" target="_blank">
            <img src="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" style="max-width: 220px" class="img-fluid rounded border" alt="imagem anexada na conversa">
          </a>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div><small class="text-muted"><?= e((string) ($msg['created_at'] ?? '')) ?></small></div>
  </div>
<?php endforeach; else: $title='Sem mensagens'; $description='Esta conversa ainda não tem mensagens.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
</div></div>
<form method="post" action="/messages/send" class="d-flex gap-2" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="receiver_id" value="<?= $otherId ?>">
  <input type="hidden" name="message_type" value="text">
  <input class="form-control" name="message_text" maxlength="2000" placeholder="Digite sua mensagem (opcional com imagem)">
  <input class="form-control form-control-sm" style="max-width:220px" type="file" name="image" accept="image/jpeg,image/png,image/webp">
  <button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button>
</form>
