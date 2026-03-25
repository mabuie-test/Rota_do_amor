<h2>Registo</h2>
<form method="post" action="/register" class="row g-3">
  <input type="hidden" name="_token" value="<?= e(\App\Core\Csrf::token()) ?>">
  <div class="col-md-6"><input class="form-control" name="first_name" placeholder="Nome" required></div>
  <div class="col-md-6"><input class="form-control" name="last_name" placeholder="Apelido" required></div>
  <div class="col-md-6"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
  <div class="col-md-6"><input class="form-control" name="phone" placeholder="25884XXXXXXX" required></div>
  <div class="col-md-4"><input class="form-control" type="date" name="birth_date" required></div>
  <div class="col-md-4">
    <select class="form-select" name="gender" required><option value="male">Masculino</option><option value="female">Feminino</option><option value="other">Outro</option></select>
  </div>
  <div class="col-md-4">
    <select class="form-select" name="relationship_goal" required><option value="friendship">Amizade</option><option value="dating">Namoro</option><option value="marriage">Casamento</option></select>
  </div>
  <div class="col-md-6"><input class="form-control" name="province_id" placeholder="ID Província" required></div>
  <div class="col-md-6"><input class="form-control" name="city_id" placeholder="ID Cidade" required></div>
  <div class="col-md-6"><input class="form-control" type="password" name="password" placeholder="Senha" required></div>
  <div class="col-md-6"><input class="form-control" type="password" name="password_confirmation" placeholder="Confirmar senha" required></div>
  <div class="col-12 form-check"><input class="form-check-input" type="checkbox" required><label class="form-check-label">Aceito os termos</label></div>
  <div class="col-12"><button class="btn btn-success">Criar conta</button></div>
</form>
