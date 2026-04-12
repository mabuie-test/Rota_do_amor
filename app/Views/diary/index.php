<?php $entries = $entries ?? []; $summary = $summary ?? []; $filters = $filters ?? []; ?>
<h3 class="mb-3">Diário do Coração</h3>
<div class="rd-card mb-3"><div class="card-body d-flex justify-content-between flex-wrap gap-2">
  <div>
    <div class="small text-muted">Último humor</div>
    <div class="fw-semibold"><?= e((string) ($summary['recent_mood'] ?? 'Sem registos')) ?></div>
  </div>
  <div>
    <div class="small text-muted">Entradas (30 dias)</div>
    <div class="fw-semibold"><?= (int) ($summary['entries_last_30_days'] ?? 0) ?></div>
  </div>
  <div>
    <div class="small text-muted">Sequência recente</div>
    <div class="fw-semibold"><?= (int) ($summary['streak_days_sample'] ?? 0) ?> dias</div>
  </div>
  <a href="/diary/new" class="btn btn-rd-primary btn-sm"><i class="fa-solid fa-pen-to-square me-1"></i>Novo registo</a>
</div></div>

<div class="rd-card mb-3"><div class="card-body">
<form method="get" class="row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label mb-1">Humor</label><input class="form-control form-control-sm" name="mood" value="<?= e((string) ($filters['mood'] ?? '')) ?>"></div>
  <div class="col-md-3"><label class="form-label mb-1">De</label><input type="date" class="form-control form-control-sm" name="from" value="<?= e((string) ($filters['from'] ?? '')) ?>"></div>
  <div class="col-md-3"><label class="form-label mb-1">Até</label><input type="date" class="form-control form-control-sm" name="to" value="<?= e((string) ($filters['to'] ?? '')) ?>"></div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Filtrar</button></div>
</form>
</div></div>

<div class="rd-card"><div class="card-body">
<?php if ($entries === []): ?>
  <p class="text-muted mb-0">Ainda não tens registos. Começa hoje o teu Diário do Coração.</p>
<?php else: ?>
  <div class="list-group list-group-flush">
    <?php foreach ($entries as $entry): ?>
      <a class="list-group-item list-group-item-action" href="/diary/<?= (int) $entry['id'] ?>">
        <div class="d-flex w-100 justify-content-between"><strong><?= e((string) ($entry['title'] ?: 'Entrada sem título')) ?></strong><small><?= e((string) $entry['created_at']) ?></small></div>
        <small class="text-muted">Humor: <?= e((string) ($entry['mood'] ?? '—')) ?> · Foco: <?= e((string) ($entry['relational_focus'] ?? '—')) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div></div>
