<?php $s = $summary ?? []; $policy = $premium_policy ?? []; ?>
<h3 class="mb-3">Radar de Visitantes</h3>
<div class="rd-card mb-3"><div class="card-body">
  <p class="small mb-1">Visitas nas últimas 24h: <strong><?= (int) ($s['total_last_24h'] ?? 0) ?></strong> · Visitantes únicos 7d: <strong><?= (int) ($s['unique_last_7d'] ?? 0) ?></strong> · Repetições 7d: <strong><?= (int) ($s['repeat_visitors_last_7d'] ?? 0) ?></strong></p>
  <p class="small text-muted mb-1">Free vê <?= (int) ($policy['free_visible_visitors'] ?? 2) ?> visitantes com detalhe e histórico reduzido. Premium desbloqueia histórico de <?= (int) ($policy['premium_history_days'] ?? 30) ?> dias.</p>
  <?php if (!empty($s['premium_locked'])): ?><div class="alert alert-info py-2 small mb-0">Conta free: pistas limitadas conforme política operacional. <a href="/premium">Tornar premium</a> para lista completa, recorrência e contexto temporal expandido.</div><?php endif; ?>
</div></div>

<div class="row g-3">
<?php foreach (($s['recent'] ?? []) as $row): ?>
  <div class="col-md-6">
    <div class="rd-card"><div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <p class="mb-1"><strong><?= e((string) ($row['visitor_name'] ?? 'Visitante')) ?></strong> <?php if ((int) ($row['visits_from_same'] ?? 1) >= 2): ?><span class="badge text-bg-warning">recorrente</span><?php endif; ?></p>
        <p class="small text-muted mb-0">Origem: <?= e((string) ($row['source_context'] ?? 'discover')) ?> · <?= e((string) ($row['created_at'] ?? '')) ?></p>
      </div>
      <?php if ((int) ($row['visitor_user_id'] ?? 0) > 0): ?><a class="btn btn-sm btn-rd-primary" href="/discover/profile/<?= (int) $row['visitor_user_id'] ?>">Ver perfil</a><?php else: ?><button class="btn btn-sm btn-outline-secondary" disabled>Bloqueado</button><?php endif; ?>
    </div></div>
  </div>
<?php endforeach; ?>
</div>
