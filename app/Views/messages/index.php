<div class="rd-page-header"><div><h3 class="rd-page-header__title"><span class="rd-page-header__icon"><i class="fa-solid fa-comments"></i></span>Mensagens</h3><p class="rd-page-header__subtitle">Conversas em tempo real com contexto relacional e segurança integrada.</p></div></div>
<?php
$activeConversationId = (int) ($active_conversation_id ?? 0);
$viewerId = (int) ($viewer_id ?? 0);
$context = $context ?? [];
$otherId = (int) ($context['other_user_id'] ?? 0);
$otherName = (string) ($context['other_user_name'] ?? 'Utilizador');
$safeDateCapabilities = is_array($context['safe_date_safety_capabilities'] ?? null) ? $context['safe_date_safety_capabilities'] : [];
$pg = $pagination ?? ['page' => 1, 'has_more' => false];
$otherStatusLabel = (int) ($context['other_online_status'] ?? 0) === 1 ? 'Online agora' : 'Offline';
$safeDateMeta = safe_date_capability_meta($safeDateCapabilities, 'messages');
?>
<div class="row g-3">
  <div class="col-lg-4"><div class="rd-card"><div class="card-body"><div class="rd-card-header"><div><h6 class="rd-card-header__title"><i class="fa-solid fa-inbox"></i>Conversas</h6><p class="rd-card-header__subtitle">Lista de threads e estado de leitura.</p></div></div>
    <form method="get" class="mb-2"><input class="form-control form-control-sm" name="q" value="<?= e((string) ($search ?? '')) ?>" placeholder="Pesquisar conversa"></form>
    <?php if (!empty($conversations)): foreach ($conversations as $conversation): ?>
      <?php $isActive = (int) $conversation['id'] === $activeConversationId; ?>
      <a href="/messages?conversation=<?= (int) $conversation['id'] ?>" class="d-block border rounded p-2 mb-2 text-decoration-none <?= $isActive ? 'border-primary bg-primary-subtle shadow-sm' : 'border-light-subtle' ?>">
        <div class="d-flex justify-content-between align-items-center">
          <strong><?= e((string) ($conversation['other_user_name'] ?? 'Utilizador')) ?></strong>
          <?php if ((int) ($conversation['unread_count'] ?? 0) > 0): ?><span class="badge bg-danger"><?= (int) $conversation['unread_count'] ?></span><?php endif; ?>
        </div>
        <div class="small text-muted"><?php if (($conversation['last_message_type'] ?? 'text') === 'image'): ?>📷 Imagem partilhada<?php else: ?><?= e((string) ($conversation['last_message'] ?? 'Sem mensagens ainda')) ?><?php endif; ?></div>
      </a>
    <?php endforeach; endif; ?>
  </div></div></div>

  <div class="col-lg-8"><div class="rd-card"><div class="card-body">
    <?php if (!empty($context)): ?>
      <div class="rd-form-section small text-muted mb-2">
        <strong><a href="/member/<?= (int) $otherId ?>" class="text-decoration-none"><?= e($otherName) ?></a></strong> · <?= e($otherStatusLabel) ?>
      </div>
      <div class="d-flex flex-wrap gap-2 mb-2">
        <a class="btn btn-sm btn-rd-soft" href="/member/<?= (int) $otherId ?>">Ver perfil</a>
        <?php if (!empty($context['can_propose_safe_date'])): ?>
          <a class="btn btn-sm btn-outline-success" href="/dates?invitee_user_id=<?= (int) $otherId ?>&conversation_id=<?= (int) $activeConversationId ?>" title="<?= e($safeDateMeta['summary']) ?>">Sugerir Encontro Seguro na conversa</a>
        <?php endif; ?>
        <?php if ((int) ($context['active_safe_date_id'] ?? 0) > 0): ?>
          <a class="btn btn-sm btn-outline-primary" href="/dates/<?= (int) $context['active_safe_date_id'] ?>">Ver encontro #<?= (int) $context['active_safe_date_id'] ?></a>
        <?php endif; ?>
        <?php if (!empty($context['next_safe_date_datetime'])): ?><span class="small text-muted">Próximo encontro: <?= e((string) $context['next_safe_date_datetime']) ?></span><?php endif; ?>
      </div>
      <?php if (!empty($context['can_propose_safe_date'])): ?>
        <div class="rd-safe-capability-note mb-2">
          <span class="small text-muted"><?= e($safeDateMeta['context']) ?></span>
          <div class="rd-safe-capability-badges mt-1">
            <?php foreach ($safeDateMeta['labels'] as $label): ?>
              <span class="rd-safe-pill"><?= e($label) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <div id="typing-indicator" class="small text-muted mb-2" style="min-height: 20px;"></div>
      <div class="rd-chat-shell mb-3">
      <div id="message-list" class="rd-chat-list">
        <?php if (!empty($messages)): foreach ($messages as $msg): ?>
          <?php $isMine = (int) ($msg['sender_id'] ?? 0) === $viewerId; ?>
          <div class="mb-2 <?= $isMine ? 'text-end' : '' ?>" data-message-id="<?= (int) $msg['id'] ?>" data-sender-id="<?= (int) $msg['sender_id'] ?>">
            <div class="small fw-semibold mb-1"><?= $isMine ? 'Tu' : e($otherName) ?></div>
            <div class="rd-chat-bubble <?= $isMine ? 'rd-chat-bubble--mine' : 'rd-chat-bubble--other' ?>">
              <?php if (($msg['message_type'] ?? 'text') === 'image'): ?><div class="small mb-1 <?= $isMine ? 'text-white-50' : 'text-muted' ?>">📷 Imagem</div><?php endif; ?>
              <?php if (($msg['message_type'] ?? 'text') !== 'image' || trim((string) ($msg['message_text'] ?? '')) !== '[imagem]'): ?><div><?= e((string) ($msg['message_text'] ?? '')) ?></div><?php endif; ?>
              <?php if (!empty($msg['attachments'])): foreach ($msg['attachments'] as $attachment): ?><a href="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" target="_blank"><img src="/<?= e((string) ($attachment['file_path'] ?? '')) ?>" style="max-width: 220px" class="img-fluid rounded border mt-2" alt="imagem anexada"></a><?php endforeach; endif; ?>
            </div>
            <div><small class="text-muted"><?= e((string) ($msg['created_at'] ?? '')) ?></small><?php if ($isMine): ?><small class="text-muted ms-2" data-receipt><?= !empty($msg['read_at']) ? 'Lida' : (!empty($msg['delivered_at']) ? 'Entregue' : 'Enviada') ?></small><?php endif; ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      </div>

      <form id="chat-form" method="post" action="/messages/send" class="d-flex gap-2 align-items-center" enctype="multipart/form-data" data-upload-fallback="single">
        <?= csrf_field() ?>
        <input type="hidden" name="receiver_id" value="<?= $otherId ?>">
        <input type="hidden" name="message_type" value="text">
        <input id="message-text" class="form-control" name="message_text" maxlength="2000" placeholder="Escreve a tua mensagem (opcional quando enviar imagem)">
      <input class="form-control form-control-sm" style="max-width:240px" type="file" name="image" accept="image/jpeg,image/png,image/webp">
      <input type="hidden" name="image_data_url" value="">
        <button class="btn btn-rd-primary"><i class="fa-solid fa-paper-plane"></i></button>
      </form>
    <?php else: ?>
      <div class="rd-soft-panel"><p class="text-muted mb-0">Selecione uma conversa para iniciar o chat em tempo real.</p></div>
    <?php endif; ?>
  </div></div></div>
</div>

<?php if (!empty($context)): ?>
<script>
(() => {
  const conversationId = <?= (int) $activeConversationId ?>;
  const viewerId = <?= (int) $viewerId ?>;
  const otherName = <?= json_encode($otherName, JSON_UNESCAPED_UNICODE) ?>;
  const csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const list = document.getElementById('message-list');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('message-text');
  const typingEl = document.getElementById('typing-indicator');
  let stream = null;
  let typingTimeout = null;

  const lastMessageId = () => {
    const nodes = list.querySelectorAll('[data-message-id]');
    if (!nodes.length) return 0;
    return parseInt(nodes[nodes.length - 1].dataset.messageId || '0', 10);
  };

  const receiptLabel = (message) => message.read_at ? 'Lida' : (message.delivered_at ? 'Entregue' : 'Enviada');
  const sanitizeRelativePath = (value) => String(value || '').replace(/^\/+/, '').replace(/[^a-zA-Z0-9_\-/.]/g, '');

  const renderMessage = (message) => {
    if (list.querySelector(`[data-message-id="${message.id}"]`)) return;
    const isMine = parseInt(message.sender_id, 10) === viewerId;
    const wrapper = document.createElement('div');
    wrapper.className = `mb-2 ${isMine ? 'text-end' : ''}`;
    wrapper.dataset.messageId = String(message.id);
    wrapper.dataset.senderId = String(message.sender_id);

    const title = document.createElement('div');
    title.className = 'small fw-semibold mb-1';
    title.textContent = isMine ? 'Tu' : otherName;

    const bubble = document.createElement('div');
    bubble.className = `rd-chat-bubble ${isMine ? 'rd-chat-bubble--mine' : 'rd-chat-bubble--other'}`;
    if (message.message_type === 'image') {
      const imgLabel = document.createElement('div');
      imgLabel.className = `small mb-1 ${isMine ? 'text-white-50' : 'text-muted'}`;
      imgLabel.textContent = '📷 Imagem';
      bubble.appendChild(imgLabel);
    }

    if (message.message_type !== 'image' || String(message.message_text || '').trim() !== '[imagem]') {
      const textNode = document.createElement('div');
      textNode.textContent = String(message.message_text || '');
      bubble.appendChild(textNode);
    }

    (message.attachments || []).forEach((attachment) => {
      const safePath = sanitizeRelativePath(attachment?.file_path);
      if (!safePath || !safePath.startsWith('storage/uploads/')) return;
      const anchor = document.createElement('a');
      anchor.href = `/${safePath}`;
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';
      const img = document.createElement('img');
      img.src = `/${safePath}`;
      img.style.maxWidth = '220px';
      img.className = 'img-fluid rounded border mt-2';
      img.alt = 'imagem anexada';
      anchor.appendChild(img);
      bubble.appendChild(anchor);
    });

    const meta = document.createElement('div');
    const time = document.createElement('small');
    time.className = 'text-muted';
    time.textContent = String(message.created_at || '');
    meta.appendChild(time);
    if (isMine) {
      const receipt = document.createElement('small');
      receipt.className = 'text-muted ms-2';
      receipt.dataset.receipt = '1';
      receipt.textContent = receiptLabel(message);
      meta.appendChild(receipt);
    }

    wrapper.appendChild(title);
    wrapper.appendChild(bubble);
    wrapper.appendChild(meta);
    list.appendChild(wrapper);
    list.scrollTop = list.scrollHeight;
  };

  const applyReceipts = (receipts) => {
    receipts.forEach((receipt) => {
      const node = list.querySelector(`[data-message-id="${receipt.id}"] [data-receipt]`);
      if (node) node.textContent = receipt.read_at ? 'Lida' : 'Entregue';
    });
  };

  const connect = () => {
    const url = `/messages/stream?conversation_id=${conversationId}&after_id=${lastMessageId()}`;
    stream = new EventSource(url);
    stream.addEventListener('chat', (event) => {
      const payload = JSON.parse(event.data || '{}');
      (payload.messages || []).forEach(renderMessage);
      applyReceipts(payload.read_receipts || []);
      const typers = payload.typing || [];
      typingEl.textContent = typers.length ? `${typers[0].user_name || otherName} está a digitar...` : '';
    });
    stream.onerror = () => {
      stream.close();
      setTimeout(connect, 1000);
    };
  };

  const sendTyping = (active) => {
    fetch('/messages/typing', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
      body: JSON.stringify({conversation_id: conversationId, typing: active})
    }).catch(() => {});
  };

  input?.addEventListener('input', () => {
    sendTyping(true);
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => sendTyping(false), 1500);
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = new FormData(form);
    const res = await fetch('/messages/send', {method: 'POST', body: data, headers: {'Accept': 'application/json'}});
    const payload = await res.json();
    if (payload.ok && payload.message) {
      renderMessage(payload.message);
      form.reset();
      sendTyping(false);
    }
  });

  connect();
})();
</script>
<?php endif; ?>
