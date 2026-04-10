<?php $context = $context ?? []; $otherId = (int) ($context['other_user_id'] ?? 0); ?>
<h3 class="mb-3">Conversa com <?= e((string) ($context['other_user_name'] ?? 'Utilizador')) ?></h3>
<div class="small text-muted mb-2">
  <?= (int) ($context['other_online_status'] ?? 0) === 1 ? 'online' : 'offline' ?>
  <?php if (!empty($context['other_last_activity_at'])): ?>· última actividade <?= e((string) $context['other_last_activity_at']) ?><?php endif; ?>
</div>
<div class="rd-card mb-3"><div class="card-body" style="max-height:420px; overflow:auto;">
<?php foreach (($messages ?? []) as $msg): ?>
  <div class="mb-2">
    <span class="rd-badge badge-active">#<?= (int) $msg['sender_id'] ?></span>
    <?php if (($msg['message_type'] ?? 'text') === 'image'): ?><span class="badge bg-secondary">imagem</span><?php endif; ?>
    <?= e($msg['message_text']) ?>
    <small class="text-muted">· <?= e($msg['created_at']) ?></small>
  </div>
<?php endforeach; ?>
</div></div>
<form method="post" action="/messages/send" class="d-flex gap-2"><?= csrf_field() ?>
  <input type="hidden" name="receiver_id" value="<?= $otherId ?>">
  <input type="hidden" name="message_type" value="text">
  <input class="form-control" name="message_text" maxlength="2000" placeholder="Digite sua mensagem">
  <button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button>
</form>
