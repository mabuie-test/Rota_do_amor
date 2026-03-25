<h2>Conversa</h2>
<?php foreach (($messages ?? []) as $msg): ?>
  <div class="mb-2"><strong>#<?= (int) $msg['sender_id'] ?>:</strong> <?= e($msg['message_text']) ?></div>
<?php endforeach; ?>
