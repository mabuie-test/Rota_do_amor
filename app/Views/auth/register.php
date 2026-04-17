<div class="row justify-content-center">
  <div class="col-xl-10">
    <div class="rd-card rd-auth-card fade-in">
      <div class="card-body p-4 p-md-5">
        <span class="rd-kicker mb-2"><i class="fa-solid fa-gem"></i>Onboarding premium</span>
        <h3 class="mb-2"><i class="fa-solid fa-sparkles me-2 text-primary"></i>Criar Conta</h3>
        <p class="text-muted mb-4">Preencha os dados para iniciar uma jornada relacional com intenção e segurança.</p>
        <form method="post" action="<?= e(url('register')) ?>" class="row g-3" data-enhanced-submit>
          <?= csrf_field() ?>
          <div class="col-md-6 input-icon-wrap"><i class="fa-regular fa-user"></i><input class="form-control" name="first_name" placeholder="Nome" required></div>
          <div class="col-md-6 input-icon-wrap"><i class="fa-regular fa-user"></i><input class="form-control" name="last_name" placeholder="Apelido" required></div>
          <div class="col-md-6 input-icon-wrap"><i class="fa-regular fa-envelope"></i><input class="form-control" type="email" name="email" placeholder="Email" required></div>
          <div class="col-md-6 input-icon-wrap"><i class="fa-solid fa-phone"></i><input class="form-control" name="phone" placeholder="25884XXXXXXX" required></div>
          <div class="col-md-4"><input class="form-control" type="date" name="birth_date" required></div>
          <div class="col-md-4"><select class="form-select" name="gender" required><option value="male">Masculino</option><option value="female">Feminino</option><option value="other">Outro</option></select></div>
          <div class="col-md-4"><select class="form-select" name="relationship_goal" required><option value="friendship">Amizade</option><option value="dating">Namoro</option><option value="marriage">Casamento</option></select></div>

          <div class="col-md-6">
            <label for="provinceSelect" class="form-label small fw-semibold"><i class="fa-solid fa-map-location-dot me-1"></i>Província</label>
            <select class="form-select" name="province_id" id="provinceSelect" required>
              <option value="">Selecione a província</option>
              <?php foreach (($provinces ?? []) as $province): ?>
                <option value="<?= (int) $province['id'] ?>"><?= e((string) $province['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="citySelect" class="form-label small fw-semibold"><i class="fa-solid fa-location-dot me-1"></i>Cidade</label>
            <select class="form-select" name="city_id" id="citySelect" required disabled>
              <option value="">Selecione a cidade</option>
              <?php foreach (($cities ?? []) as $city): ?>
                <option value="<?= (int) $city['id'] ?>" data-province="<?= (int) $city['province_id'] ?>" hidden><?= e((string) $city['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">As cidades disponíveis dependem da província escolhida.</small>
          </div>

          <div class="col-md-6">
            <div class="input-icon-wrap">
              <i class="fa-solid fa-lock"></i>
              <input class="form-control" id="registerPassword" type="password" name="password" placeholder="Senha forte" autocomplete="new-password" required>
            </div>
            <div class="rd-password-meter mt-2" data-password-meter aria-live="polite">
              <div class="rd-password-meter__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <span class="rd-password-meter__fill"></span>
              </div>
              <small class="rd-password-meter__text text-muted">Força da senha: —</small>
            </div>
          </div>
          <div class="col-md-6 input-icon-wrap"><i class="fa-solid fa-lock"></i><input class="form-control" type="password" name="password_confirmation" placeholder="Confirmar senha" required></div>
          <div class="col-12 form-check"><input class="form-check-input" type="checkbox" required><label class="form-check-label">Aceito os termos e a política de privacidade</label></div>
          <div class="col-12 d-grid"><button class="btn btn-rd-primary btn-lg">Registar</button></div>
          <div class="col-12 text-center">
            <p class="small text-muted mb-0">Já tens uma conta? <a href="/login" class="fw-semibold text-decoration-none">Entrar agora</a></p>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
