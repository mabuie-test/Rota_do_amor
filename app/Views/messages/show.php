<?php $context = $context ?? []; $otherId = (int) ($context['other_user_id'] ?? 0); $pg = $pagination ?? ['page' => 1, 'has_more' => false]; ?>
<h3 class="mb-3">Conversa com <?= e((string) ($context['other_user_name'] ?? 'Utilizador')) ?></h3>
<div class="small text-muted mb-2">
  <?= (int) ($context['other_online_status'] ?? 0) === 1 ? 'online' : 'offline' ?>
  <?php if (!empty($context['other_last_activity_at'])): ?>· última actividade <?= e((string) $context['other_last_activity_at']) ?><?php endif; ?>
  <?php if ((int) ($context['other_is_verified'] ?? 0) === 1): ?>· verificado<?php endif; ?>
  <?php if (!empty($context['other_user_status'])): ?>· estado <?= e((string) $context['other_user_status']) ?><?php endif; ?>
</div>
<a class="btn btn-sm btn-outline-secondary mb-2" href="/messages?conversation=<?= (int) ($context['id'] ?? 0) ?>">Abrir vista completa da inbox</a>
<?php if (!empty($pg['has_more'])): ?><a class="btn btn-sm btn-outline-secondary mb-2" href="?page=<?= (int) ($pg['page'] + 1) ?>">Carregar histórico anterior</a><?php endif; ?>
<div class="rd-card mb-3"><div class="card-body" style="max-height:420px; overflow:auto;">
<?php if (!empty($messages)): foreach (($messages ?? []) as $msg): ?>
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
<?php endforeach; else: $title='Sem mensagens'; $description='Esta conversa ainda não tem mensagens.'; require dirname(__DIR__).'/partials/empty-state.php'; endif; ?>
</div></div>
<form method="post" action="/messages/send" class="d-flex gap-2" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="receiver_id" value="<?= $otherId ?>">
  <input type="hidden" name="message_type" value="text">
  <input class="form-control" name="message_text" maxlength="2000" placeholder="Digite sua mensagem (opcional com imagem)">
  <input class="form-control form-control-sm" style="max-width:220px" type="file" name="image" accept="image/jpeg,image/png,image/webp">
  <button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button>
</form>
