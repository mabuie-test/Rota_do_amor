<?php $entries = $entries ?? []; $summary = $summary ?? []; $filters = $filters ?? []; $cta = $summary['cta'] ?? []; $prompt = $summary['journey_prompt'] ?? []; ?>
<h3 class="mb-3">Diário do Coração</h3>
<div class="rd-card mb-3"><div class="card-body d-flex justify-content-between flex-wrap gap-3 align-items-start">
  <div><div class="small text-muted">Último humor</div><div class="fw-semibold"><?= e((string) ($summary['recent_mood'] ?? 'Sem registos')) ?></div></div>
  <div><div class="small text-muted">Entradas (7 / 30 dias)</div><div class="fw-semibold"><?= (int) ($summary['entries_last_7_days'] ?? 0) ?> / <?= (int) ($summary['entries_last_30_days'] ?? 0) ?></div></div>
  <div><div class="small text-muted">Consistência emocional</div><div class="fw-semibold"><?= (int) ($summary['emotional_consistency_signal'] ?? 0) ?>%</div></div>
  <div><div class="small text-muted">Dias sem escrever</div><div class="fw-semibold"><?= (int) ($summary['days_since_last_entry'] ?? 0) ?></div></div>
  <a href="/diary/new" class="btn btn-rd-primary btn-sm"><i class="fa-solid fa-pen-to-square me-1"></i><?= e((string) ($cta['action_label'] ?? 'Novo registo')) ?></a>
</div></div>
<div class="small text-muted mb-3">Privacidade por desenho: conteúdo íntimo não é visível para admin comum nem super admin; apenas métricas institucionais agregadas.</div>

<?php if (($prompt['message'] ?? '') !== ''): ?><div class="alert alert-info py-2 mb-3"><strong>Jornada emocional:</strong> <?= e((string) $prompt['message']) ?></div><?php endif; ?>
<div class="alert alert-light border py-2 mb-3"><strong><?= e((string) ($cta['title'] ?? 'Escreve hoje')) ?></strong><div class="small text-muted"><?= e((string) ($cta['copy'] ?? 'Mantém o teu espaço emocional actualizado.')) ?></div></div>

<?php if (!empty($summary['mood_distribution_30_days'])): ?><div class="rd-card mb-3"><div class="card-body"><h6 class="mb-2">Distribuição de humor (30 dias)</h6><div class="d-flex gap-2 flex-wrap"><?php foreach (($summary['mood_distribution_30_days'] ?? []) as $mood): ?><span class="rd-badge badge-active"><?= e((string) $mood['mood_label']) ?>: <?= (int) $mood['total'] ?></span><?php endforeach; ?></div></div></div><?php endif; ?>

<div class="rd-card mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label mb-1">Humor</label><input class="form-control form-control-sm" name="mood" value="<?= e((string) ($filters['mood'] ?? '')) ?>"></div><div class="col-md-3"><label class="form-label mb-1">De</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>"></div><div class="col-md-3"><label class="form-label mb-1">Até</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>"></div><div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div></form></div></div>

<div class="rd-card"><div class="card-body"><?php if ($entries === []): ?><p class="text-muted mb-0">Ainda não tens registos. Começa hoje o teu Diário do Coração.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($entries as $entry): ?><a class="list-group-item list-group-item-action" href="/diary/<?= (int) $entry['id'] ?>"><div class="d-flex w-100 justify-content-between"><strong><?= e((string) ($entry['title'] ?: 'Entrada sem título')) ?></strong><small><?= e((string) $entry['created_at']) ?></small></div><small class="text-muted">Humor: <?= e((string) ($entry['mood'] ?? '—')) ?> · Foco: <?= e((string) ($entry['relational_focus'] ?? '—')) ?></small></a><?php endforeach; ?></div><?php endif; ?></div></div>
