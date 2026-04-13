<?php $duel = $duel ?? []; $options = $duel['options'] ?? []; ?>
<h3 class="mb-3">Duelo de Compatibilidade</h3>
<?php if (count($options) < 2): ?>
  <div class="alert alert-info">Sem candidatos suficientes hoje. Volta mais tarde para novo duelo.</div>
<?php else: ?>
  <div class="rd-card mb-3"><div class="card-body">
    <p class="small text-muted mb-2">Escolhe o perfil com mais potencial para ti hoje.</p>
    <div class="row g-3">
      <?php foreach ($options as $opt): $b = json_decode((string) ($opt['compatibility_breakdown_snapshot'] ?? '{}'), true) ?: []; ?>
        <div class="col-md-6"><div class="border rounded p-3 h-100">
          <h6><?= e((string) ($opt['candidate_name'] ?? 'Perfil')) ?></h6>
          <p class="small mb-1">Compatibilidade snapshot: <strong><?= (float) ($opt['compatibility_score_snapshot'] ?? 0) ?>%</strong></p>
          <p class="small mb-2">Intenção: <?= (int) ($b['intention_alignment_percent'] ?? 0) ?>% · Ritmo: <?= (int) ($b['pace_alignment_percent'] ?? 0) ?>%</p>
          <form method="post" action="/compatibility-duel/vote" class="d-inline"><?= csrf_field() ?><input type="hidden" name="duel_id" value="<?= (int) ($duel['id'] ?? 0) ?>"><input type="hidden" name="selected_option_id" value="<?= (int) $opt['id'] ?>"><button class="btn btn-sm btn-rd-primary">Escolher</button></form>
          <a class="btn btn-sm btn-rd-soft" href="/discover/profile/<?= (int) ($opt['candidate_user_id'] ?? 0) ?>">Ver perfil</a>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div></div>
<?php endif; ?>
