<?php
$kind = $kind ?? 'active';
$map = [
    'premium' => 'badge-premium',
    'verified' => 'badge-verified',
    'boosted' => 'badge-boosted',
    'pending' => 'badge-pending',
    'failed' => 'badge-failed',
    'paid' => 'badge-paid',
    'active' => 'badge-active',
];
$class = $map[$kind] ?? 'badge-active';
?>
<span class="rd-badge <?= e($class) ?>"><?= e($label ?? ucfirst($kind)) ?></span>
