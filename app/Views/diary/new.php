<h3 class="mb-3">Novo registo no Diário do Coração</h3>
<div class="rd-card"><div class="card-body">
<form method="post" action="/diary"><?= csrf_field() ?>
  <div class="mb-2"><label class="form-label">Título (opcional)</label><input class="form-control" name="title"></div>
  <div class="mb-2"><label class="form-label">Conteúdo</label><textarea class="form-control" name="content" rows="8" required></textarea></div>
  <div class="row g-2">
    <div class="col-md-4"><label class="form-label">Humor</label><input class="form-control" name="mood" placeholder="sereno, ansioso, feliz..."></div>
    <div class="col-md-4"><label class="form-label">Estado emocional</label><input class="form-control" name="emotional_state"></div>
    <div class="col-md-4"><label class="form-label">Foco relacional</label><input class="form-control" name="relational_focus"></div>
  </div>
  <div class="mt-2"><label class="form-label">Tags (separadas por vírgula)</label><input class="form-control" name="tags"></div>
  <div class="mt-3 d-flex gap-2"><button class="btn btn-rd-primary">Guardar</button><a href="/diary" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
</div></div>
