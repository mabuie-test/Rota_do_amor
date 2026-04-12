<?php
$items = $items ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 25];
$totals = $totals ?? [];
$statuses = $statuses ?? [];
$safetyLevels = $safety_levels ?? [];
$policy = $premium_policy ?? [];
?>

<h3 class="mb-3">Encontro Seguro · Administração</h3>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Em aberto</div><div class="value"><?= (int) ($totals['in_open_state'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Concluídos (30d)</div><div class="value"><?= (int) ($totals['completed_30d'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card rd-kpi"><div class="card-body"><div class="small text-muted">Sinais de segurança (30d)</div><div class="value"><?= (int) ($totals['safety_signals_30d'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="rd-card"><div class="card-body small">
    <strong>Camada Premium</strong><br>
    Free: <?= (int) ($policy['free_daily_limit'] ?? 0) ?>/dia · Abertos: <?= (int) ($policy['max_open_free'] ?? 0) ?><br>
    Premium: <?= (int) ($policy['premium_daily_limit'] ?? 0) ?>/dia · Abertos: <?= (int) ($policy['max_open_premium'] ?? 0) ?><br>
    Premium guard: <strong><?= !empty($policy['premium_guard_enabled']) ? 'ativo' : 'desligado' ?></strong>
  </div></div></div>
</div>

<div class="rd-card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-2"><label class="form-label mb-1">Status</label><select class="form-select form-select-sm" name="status"><option value="">todos</option><?php foreach ($statuses as $status): ?><option value="<?= e((string) $status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e((string) $status) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label mb-1">Nível segurança</label><select class="form-select form-select-sm" name="safety_level"><option value="">todos</option><?php foreach ($safetyLevels as $level): ?><option value="<?= e((string) $level) ?>" <?= ($filters['safety_level'] ?? '') === $level ? 'selected' : '' ?>><?= e((string) $level) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label mb-1">Iniciador (ID)</label><input class="form-control form-control-sm" name="initiator_user_id" value="<?= e((string) ($filters['initiator_user_id'] ?? '')) ?>"></div>
    <div class="col-md-2"><label class="form-label mb-1">Convidado (ID)</label><input class="form-control form-control-sm" name="invitee_user_id" value="<?= e((string) ($filters['invitee_user_id'] ?? '')) ?>"></div>
    <div class="col-md-2"><label class="form-label mb-1">De</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>"></div>
    <div class="col-md-2"><label class="form-label mb-1">Até</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>"></div>
    <div class="col-md-2"><label class="form-label mb-1">Por página</label><select class="form-select form-select-sm" name="per_page"><?php foreach ([25,50,100] as $size): ?><option value="<?= $size ?>" <?= (int) ($pagination['per_page'] ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div>
  </form>
</div></div>

<div class="rd-card"><div class="card-body table-responsive">
  <p class="small text-muted">Total: <strong><?= (int) ($pagination['total'] ?? 0) ?></strong> · Página <?= (int) ($pagination['page'] ?? 1) ?> / <?= (int) ($pagination['total_pages'] ?? 1) ?></p>
  <table class="table table-modern align-middle">
    <thead><tr><th>ID</th><th>Par</th><th>Status</th><th>Segurança</th><th>Data proposta</th><th>Criado</th><th></th></tr></thead>
    <tbody>
    <?php if ($items === []): ?><tr><td colspan="7" class="text-muted small">Sem encontros para os filtros aplicados.</td></tr><?php endif; ?>
    <?php foreach ($items as $item): ?>
      <tr>
        <td>#<?= (int) $item['id'] ?></td>
        <td>
          <div><a href="<?= e(url('/admin/users/' . (int) $item['initiator_user_id'])) ?>">#<?= (int) $item['initiator_user_id'] ?></a> · <?= e((string) ($item['initiator_name'] ?? '')) ?></div>
          <div><a href="<?= e(url('/admin/users/' . (int) $item['invitee_user_id'])) ?>">#<?= (int) $item['invitee_user_id'] ?></a> · <?= e((string) ($item['invitee_name'] ?? '')) ?></div>
        </td>
        <td><strong><?= e((string) ($item['status'] ?? '')) ?></strong></td>
        <td><span class="rd-badge badge-active"><?= e((string) ($item['safety_level'] ?? '')) ?></span><?php if (($item['safety_signal_level'] ?? 'none') !== 'none'): ?><br><span class="rd-badge badge-warning mt-1">Sinal: <?= e((string) $item['safety_signal_level']) ?></span><?php endif; ?></td>
        <td><?= e((string) ($item['proposed_datetime'] ?? '')) ?><br><small class="text-muted"><?= e((string) ($item['proposed_location'] ?? '')) ?></small></td>
        <td><?= e((string) ($item['created_at'] ?? '')) ?></td>
        <td><a class="btn btn-sm btn-rd-soft" href="<?= e(url('/admin/safe-dates/' . (int) $item['id'])) ?>">Detalhe</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php $prev = max(1, ((int) ($pagination['page'] ?? 1)) - 1); $next = min((int) ($pagination['total_pages'] ?? 1), ((int) ($pagination['page'] ?? 1)) + 1); ?>
  <a class="btn btn-sm btn-outline-secondary <?= ((int) ($pagination['page'] ?? 1) <= 1) ? 'disabled' : '' ?>" href="?<?= http_build_query(array_merge($filters, ['page' => $prev])) ?>">Anterior</a>
  <a class="btn btn-sm btn-outline-secondary <?= ((int) ($pagination['page'] ?? 1) >= (int) ($pagination['total_pages'] ?? 1)) ? 'disabled' : '' ?>" href="?<?= http_build_query(array_merge($filters, ['page' => $next])) ?>">Próxima</a>
</div></div>
