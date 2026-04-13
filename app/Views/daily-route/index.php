<?php $route = $daily_route ?? []; $tasks = $route['tasks'] ?? []; ?>
<h3 class="mb-3">Rota Diária</h3>

<div class="rd-card mb-3"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h6 class="mb-0">Rota de hoje · <?= e((string) ($route['route_date'] ?? date('Y-m-d'))) ?></h6>
    <span class="rd-badge badge-active">Sequência: <?= (int) ($route['streak_current'] ?? 0) ?> dias · Melhor: <?= (int) ($route['streak_best'] ?? 0) ?></span>
  </div>
  <p class="small text-muted mb-2"><?= (int) ($route['progress_completed'] ?? 0) ?> de <?= (int) ($route['progress_total'] ?? 0) ?> passos concluídos · Progresso <?= (int) ($route['progress_percent'] ?? 0) ?>%</p>
  <div class="progress mb-3"><div class="progress-bar" role="progressbar" style="width: <?= (int) ($route['progress_percent'] ?? 0) ?>%"></div></div>
  <p class="small mb-3">Recompensa de hoje: <strong><?= e((string) ($route['reward_label'] ?? 'Mini boost + badge')) ?></strong></p>

  <?php if ($tasks === []): ?>
    <div class="alert alert-light border mb-3">Sem tarefas disponíveis no momento. Atualiza a página para regenerar a rota do dia.</div>
  <?php else: ?>
    <div class="list-group mb-3">
      <?php foreach ($tasks as $task): ?>
        <div class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold"><?= e((string) ($task['title'] ?? 'Tarefa')) ?></div>
            <div class="small text-muted"><?= e((string) ($task['description'] ?? '')) ?></div>
            <div class="small text-muted"><?= (int) ($task['current_value'] ?? 0) ?>/<?= (int) ($task['target_value'] ?? 1) ?></div>
          </div>
          <span class="badge <?= (($task['status'] ?? 'pending') === 'completed') ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (($task['status'] ?? 'pending') === 'completed') ? 'Concluída' : 'Pendente' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($route['can_claim_reward'])): ?>
    <form method="POST" action="/daily-route/claim-reward">
      <?= csrf_field() ?>
      <button class="btn btn-rd-primary" type="submit"><i class="fa-solid fa-gift me-1"></i>Resgatar recompensa</button>
    </form>
  <?php elseif (($route['reward_status'] ?? '') === 'claimed'): ?>
    <div class="alert alert-success py-2 mb-0">Recompensa já aplicada hoje. Mantém a consistência amanhã.</div>
  <?php else: ?>
    <div class="alert alert-light border py-2 mb-0">Conclui todas as tarefas para desbloquear a recompensa. Dica: mantém foco nas tarefas sociais para fechar a rota mais rápido.</div>
  <?php endif; ?>
</div></div>
