<?php
$duel = $duel ?? [];
$options = $duel['options'] ?? [];
$policy = $premium_policy ?? [];
$selectedOptionId = (int) ($duel['selected_option_id'] ?? 0);
$selectedDuelId = (int) ($selected_duel_id ?? 0);
$currentDuelId = (int) ($duel['id'] ?? 0);
?>
<h3 class="mb-3">Duelo de Compatibilidade</h3>
<div class="rd-card mb-3"><div class="card-body small">Plano free/premium: <strong><?= (int) ($policy['free_daily_duels'] ?? 1) ?></strong> / <strong><?= (int) ($policy['premium_daily_duels'] ?? 1) ?></strong> duelos por dia · Insights premium <strong><?= !empty($policy['premium_insights_enabled']) ? 'ativos' : 'off' ?></strong>.</div></div>
<?php if ($selectedDuelId > 0 && $currentDuelId > 0): ?>
  <div class="small text-muted mb-2">Contexto de duelo: #<?= $currentDuelId === $selectedDuelId ? $currentDuelId : $selectedDuelId ?></div>
<?php endif; ?>
<?php if (count($options) < 2): ?>
  <div class="alert alert-info">Sem candidatos suficientes hoje. Volta mais tarde para novo duelo.</div>
<?php else: ?>
  <div class="rd-card mb-3 <?= ($selectedDuelId > 0 && $selectedDuelId === $currentDuelId) ? 'rd-duel-highlight' : '' ?>" id="duel-<?= $currentDuelId ?>"><div class="card-body">
    <p class="small text-muted mb-2">Escolhe o perfil com mais potencial para ti hoje.</p>
    <div class="row g-3">
      <?php foreach ($options as $opt): $b = json_decode((string) ($opt['compatibility_breakdown_snapshot'] ?? '{}'), true) ?: []; ?>
        <div class="col-md-6"><div class="border rounded p-3 h-100">
          <h6><?= e((string) ($opt['candidate_name'] ?? 'Perfil')) ?></h6>
          <p class="small mb-1">Compatibilidade snapshot: <strong><?= (float) ($opt['compatibility_score_snapshot'] ?? 0) ?>%</strong></p>
          <p class="small mb-2">Intenção: <?= (int) ($b['intention_alignment_percent'] ?? 0) ?>% · Ritmo: <?= (int) ($b['pace_alignment_percent'] ?? 0) ?>%</p>
          <form method="post" action="/compatibility-duel/vote" class="d-inline"><?= csrf_field() ?><input type="hidden" name="duel_id" value="<?= $currentDuelId ?>"><input type="hidden" name="selected_option_id" value="<?= (int) $opt['id'] ?>"><button class="btn btn-sm btn-rd-primary"><?= $selectedOptionId === (int) $opt['id'] ? 'Escolhido' : 'Escolher' ?></button></form>
          <a class="btn btn-sm btn-rd-soft" href="/member/<?= (int) ($opt['candidate_user_id'] ?? 0) ?>">Ver perfil</a>
          <?php if ($selectedOptionId === (int) $opt['id']): ?>
            <div class="mt-2 d-flex gap-2 flex-wrap">
              <?php foreach (['view_profile' => 'Ver Perfil', 'invite' => 'Convidar', 'favorite' => 'Favoritar'] as $type => $label): ?>
                <button type="button" class="btn btn-sm btn-outline-primary duel-action-btn" data-duel-id="<?= $currentDuelId ?>" data-action="<?= e($type) ?>"><?= e($label) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div></div>
  <script>
    document.querySelectorAll('.duel-action-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        fetch('/compatibility-duel/action', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: '_token=<?= e(csrf_token()) ?>&duel_id=' + encodeURIComponent(button.dataset.duelId) + '&action_type=' + encodeURIComponent(button.dataset.action)})
          .then(function () { button.classList.remove('btn-outline-primary'); button.classList.add('btn-success'); })
          .catch(function () { button.classList.add('btn-outline-danger'); });
      });
    });
  </script>
<?php endif; ?>
