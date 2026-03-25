<?php
$dir = dirname(__DIR__) . '/storage/uploads/temp';
$removed = 0;
foreach (glob($dir . '/*') ?: [] as $file) {
    if (is_file($file) && filemtime($file) < strtotime('-1 day')) {
        unlink($file);
        $removed++;
    }
}
echo "Removed temp files: {$removed}\n";
