<?php
$kind = $kind ?? 'active';
$map = [
    'premium' => ['badge-premium', 'fa-crown'],
    'verified' => ['badge-verified', 'fa-circle-check'],
    'boosted' => ['badge-boosted', 'fa-bolt'],
    'pending' => ['badge-pending', 'fa-clock'],
    'failed' => ['badge-failed', 'fa-circle-xmark'],
    'paid' => ['badge-paid', 'fa-money-bill-wave'],
    'active' => ['badge-active', 'fa-sparkles'],
];
[$class, $icon] = $map[$kind] ?? ['badge-active', 'fa-sparkles'];
?>
<span class="rd-badge <?= e($class) ?>"><i class="fa-solid <?= e($icon) ?>"></i><?= e($label ?? ucfirst($kind)) ?></span>
