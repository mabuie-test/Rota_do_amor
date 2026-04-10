<h3 class="mb-3"><i class="fa-solid fa-id-card me-2"></i>Verificação de Identidade</h3>
<div class="rd-card"><div class="card-body">
  <p class="text-muted">Envie selfie e documento para ganhar badge de confiança.</p>
  <form method="post" action="/verification/submit" class="row g-2"><?= csrf_field() ?>
    <div class="col-md-6"><input class="form-control" name="document_image_path" placeholder="Caminho do documento"></div>
    <div class="col-md-6"><input class="form-control" name="selfie_image_path" placeholder="Caminho da selfie"></div>
    <div class="col-12"><button class="btn btn-rd-primary">Enviar para análise</button></div>
  </form>
</div></div>
