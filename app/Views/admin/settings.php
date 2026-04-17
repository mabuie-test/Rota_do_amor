<h3 class="rd-page-header__title mb-3"><span class="rd-page-header__icon"><i class="fa-solid fa-sliders"></i></span>Configurações institucionais</h3>
<p class="rd-page-header__subtitle">Governança de chaves operacionais, valores sistémicos e tipagem de configuração com atualização segura.</p>

<?php foreach (($warnings ?? []) as $warning): ?>
  <div class="alert alert-warning rd-alert py-2"><i class="fa-solid fa-triangle-exclamation"></i><span><?= e((string) $warning) ?></span></div>
<?php endforeach; ?>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="rd-card h-100">
      <div class="card-body">
        <div class="rd-card-header">
          <div>
            <h6 class="rd-card-header__title"><i class="fa-solid fa-table-list"></i>Matriz de settings</h6>
            <p class="rd-card-header__subtitle">Valores atuais carregados no sistema.</p>
          </div>
        </div>
        <div class="table-responsive rd-table-shell">
          <table class="table table-modern align-middle">
            <thead>
              <tr>
                <th><i class="fa-solid fa-key me-1"></i>Chave</th>
                <th><i class="fa-solid fa-pen-ruler me-1"></i>Valor</th>
                <th><i class="fa-solid fa-code me-1"></i>Tipo</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach (($settings ?? []) as $s): ?>
              <tr>
                <td class="fw-semibold"><?= e($s['setting_key']) ?></td>
                <td><span class="rd-supporting-text"><?= e($s['setting_value']) ?></span></td>
                <td><span class="rd-badge badge-active"><?= e($s['value_type']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="rd-card h-100">
      <div class="card-body">
        <div class="rd-card-header">
          <div>
            <h6 class="rd-card-header__title"><i class="fa-solid fa-gear"></i>Atualizar setting</h6>
            <p class="rd-card-header__subtitle">Alteração incremental e tipada de chaves existentes.</p>
          </div>
        </div>
        <form method="post" action="/admin/settings/update" class="rd-form-section d-flex flex-column gap-2">
          <?= csrf_field() ?>
          <div>
            <label class="form-label">Setting key</label>
            <input class="form-control" name="setting_key" placeholder="setting_key" required>
          </div>
          <div>
            <label class="form-label">Novo valor</label>
            <input class="form-control" name="setting_value" placeholder="setting_value" required>
          </div>
          <div>
            <label class="form-label">Tipo</label>
            <select class="form-select" name="value_type">
              <option>string</option><option>int</option><option>bool</option><option>json</option>
            </select>
          </div>
          <button class="btn btn-rd-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar alteração</button>
        </form>
      </div>
    </div>
  </div>
</div>
