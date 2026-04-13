<?php if (empty($profile)): ?>
<div class="alert alert-warning">Perfil indisponível.</div>
<?php else: ?>
<div class="rd-card"><div class="card-body">
  <h4><?= e((string) (($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></h4>
  <p class="small text-muted mb-1"><?= e((string) ($profile['city_name'] ?? '')) ?> · <?= e((string) ($profile['relationship_goal'] ?? '')) ?></p>
  <p class="small mb-1">Compatibilidade: <strong><?= (float) ($profile['_compatibility'] ?? 0) ?>%</strong></p>
  <p><?= nl2br(e((string) ($profile['bio'] ?? 'Sem bio.'))) ?></p>
  <div class="d-flex gap-2"><a class="btn btn-sm btn-rd-soft" href="/discover">Voltar discovery</a><a class="btn btn-sm btn-rd-primary" href="/invites/received">Convidar</a></div>
</div></div>
<?php endif; ?>
