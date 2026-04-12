<?php $entry = $entry ?? []; ?>
<h3 class="mb-3">Diário do Coração</h3>
<div class="rd-card mb-3"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h5 class="mb-1"><?= e((string) ($entry['title'] ?: 'Entrada sem título')) ?></h5>
      <div class="small text-muted">Criado em <?= e((string) ($entry['created_at'] ?? '')) ?> · Atualizado em <?= e((string) ($entry['updated_at'] ?? '')) ?></div>
    </div>
    <form method="post" action="/diary/<?= (int) ($entry['id'] ?? 0) ?>/delete" onsubmit="return confirm('Remover este registo?');"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Apagar</button></form>
  </div>
  <hr>
  <p style="white-space: pre-wrap;"><?= e((string) ($entry['content'] ?? '')) ?></p>
  <div class="small text-muted">Humor: <?= e((string) ($entry['mood'] ?? '—')) ?> · Estado: <?= e((string) ($entry['emotional_state'] ?? '—')) ?> · Foco: <?= e((string) ($entry['relational_focus'] ?? '—')) ?></div>
</div></div>

<div class="rd-card"><div class="card-body">
  <h6>Editar entrada</h6>
  <form method="post" action="/diary/<?= (int) ($entry['id'] ?? 0) ?>"><?= csrf_field() ?>
    <div class="mb-2"><input class="form-control" name="title" value="<?= e((string) ($entry['title'] ?? '')) ?>"></div>
    <div class="mb-2"><textarea class="form-control" name="content" rows="7" required><?= e((string) ($entry['content'] ?? '')) ?></textarea></div>
    <div class="row g-2">
      <div class="col-md-4"><input class="form-control" name="mood" value="<?= e((string) ($entry['mood'] ?? '')) ?>" placeholder="Humor"></div>
      <div class="col-md-4"><input class="form-control" name="emotional_state" value="<?= e((string) ($entry['emotional_state'] ?? '')) ?>" placeholder="Estado emocional"></div>
      <div class="col-md-4"><input class="form-control" name="relational_focus" value="<?= e((string) ($entry['relational_focus'] ?? '')) ?>" placeholder="Foco relacional"></div>
    </div>
    <div class="mt-2"><input class="form-control" name="tags" value="<?= e((string) implode(',', json_decode((string) ($entry['tags_json'] ?? '[]'), true) ?: [])) ?>" placeholder="tags"></div>
    <button class="btn btn-rd-primary mt-3">Salvar alterações</button>
  </form>
</div></div>
