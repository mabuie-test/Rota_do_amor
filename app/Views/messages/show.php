<h3 class="mb-3">Conversa</h3>
<div class="rd-card mb-3"><div class="card-body" style="max-height:420px; overflow:auto;">
<?php foreach (($messages ?? []) as $msg): ?>
  <div class="mb-2"><span class="rd-badge badge-active">#<?= (int) $msg['sender_id'] ?></span> <?= e($msg['message_text']) ?> <small class="text-muted">· <?= e($msg['created_at']) ?></small></div>
<?php endforeach; ?>
</div></div>
<form method="post" action="/messages/send" class="d-flex gap-2"><?= csrf_field() ?><input class="form-control" name="message_text" placeholder="Digite sua mensagem"><button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button></form>
