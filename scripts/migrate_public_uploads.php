<?php

declare(strict_types=1);

$legacyRoot = dirname(__DIR__) . '/storage/uploads';
$publicRoot = dirname(__DIR__) . '/public/storage/uploads';

if (!is_dir($legacyRoot)) {
    echo "Legacy uploads directory not found: {$legacyRoot}" . PHP_EOL;
    exit(0);
}

if (!is_dir($publicRoot) && !mkdir($publicRoot, 0755, true) && !is_dir($publicRoot)) {
    fwrite(STDERR, "Failed to create public uploads root: {$publicRoot}" . PHP_EOL);
    exit(1);
}

$copied = 0;
$skippedExisting = 0;
$skippedMissing = 0;
$errors = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($legacyRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if (!$item->isFile()) {
        continue;
    }

    $source = $item->getPathname();
    if (!is_file($source)) {
        $skippedMissing++;
        continue;
    }

    $relative = ltrim(str_replace('\\', '/', substr($source, strlen($legacyRoot))), '/');
    if ($relative === '') {
        $skippedMissing++;
        continue;
    }

    $destination = $publicRoot . '/' . $relative;
    $destinationDir = dirname($destination);

    if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
        $errors++;
        fwrite(STDERR, "Failed to create destination directory: {$destinationDir}" . PHP_EOL);
        continue;
    }

    if (is_file($destination)) {
        $skippedExisting++;
        continue;
    }

    if (!copy($source, $destination)) {
        $errors++;
        fwrite(STDERR, "Failed to copy: {$source} -> {$destination}" . PHP_EOL);
        continue;
    }

    $copied++;
}

echo "Migration finished." . PHP_EOL;
echo "Copied: {$copied}" . PHP_EOL;
echo "Skipped existing: {$skippedExisting}" . PHP_EOL;
echo "Skipped missing: {$skippedMissing}" . PHP_EOL;
echo "Errors: {$errors}" . PHP_EOL;
