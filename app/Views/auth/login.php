<h2>Entrar</h2>
<form method="post" action="/login" class="row g-3">
  <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
  <div class="col-12"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
  <div class="col-12"><input class="form-control" type="password" name="password" placeholder="Senha" required></div>
  <div class="col-12"><button class="btn btn-primary">Entrar</button></div>
</form>
