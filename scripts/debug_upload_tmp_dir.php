<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Config;

function out(string $line): void
{
    echo $line . PHP_EOL;
}

function normalizeCandidate(string $value): string
{
    return rtrim(str_replace('\\', '/', trim($value)), '/');
}

function describePath(string $path): array
{
    if ($path === '') {
        return [
            'path' => '(vazio)',
            'exists' => false,
            'is_dir' => false,
            'writable' => false,
            'readable' => false,
            'owner' => null,
            'perms_octal' => null,
            'realpath' => null,
        ];
    }

    $exists = file_exists($path);
    $isDir = is_dir($path);
    $perms = $exists ? @fileperms($path) : false;

    return [
        'path' => $path,
        'exists' => $exists,
        'is_dir' => $isDir,
        'writable' => $isDir ? is_writable($path) : false,
        'readable' => $isDir ? is_readable($path) : false,
        'owner' => $exists ? @fileowner($path) : null,
        'perms_octal' => $perms !== false ? substr(sprintf('%o', $perms), -4) : null,
        'realpath' => $exists ? @realpath($path) : null,
    ];
}

out('== Upload temp dir diagnostic ==');
out('Timestamp (UTC): ' . gmdate('Y-m-d H:i:s'));
out('PHP Version: ' . PHP_VERSION);
out('SAPI: ' . PHP_SAPI);
out('Loaded php.ini: ' . (php_ini_loaded_file() ?: '(nenhum)'));
out('Additional .ini files: ' . (php_ini_scanned_files() ?: '(nenhum)'));
out('open_basedir: ' . ((string) ini_get('open_basedir') ?: '(vazio)'));
out('');

$uploadTmpIni = normalizeCandidate((string) ini_get('upload_tmp_dir'));
$sessionSavePathRaw = (string) ini_get('session.save_path');
$sessionParts = explode(';', $sessionSavePathRaw);
$sessionSavePath = normalizeCandidate((string) end($sessionParts));
$sysTempDir = normalizeCandidate((string) sys_get_temp_dir());
$envFallback = normalizeCandidate((string) Config::env('UPLOAD_FALLBACK_TMP_DIR', ''));
$appUploadRoot = normalizeCandidate(dirname(__DIR__) . '/public/storage/uploads');

$candidates = [];
if ($envFallback !== '') {
    $candidates['UPLOAD_FALLBACK_TMP_DIR (.env)'] = $envFallback;
}
if ($uploadTmpIni !== '') {
    $candidates['upload_tmp_dir (php.ini)'] = $uploadTmpIni;
}
if ($sessionSavePath !== '') {
    $candidates['session.save_path (php.ini)'] = $sessionSavePath;
}
if ($sysTempDir !== '') {
    $candidates['sys_get_temp_dir()'] = $sysTempDir;
}
$candidates['/tmp'] = '/tmp';
$candidates['public/storage/uploads'] = $appUploadRoot;

out('[Candidates]');
foreach ($candidates as $label => $path) {
    $meta = describePath($path);
    out('- ' . $label . ': ' . $meta['path']);
    out('  exists=' . ($meta['exists'] ? 'yes' : 'no')
        . ' dir=' . ($meta['is_dir'] ? 'yes' : 'no')
        . ' writable=' . ($meta['writable'] ? 'yes' : 'no')
        . ' readable=' . ($meta['readable'] ? 'yes' : 'no')
        . ' perms=' . ($meta['perms_octal'] ?? 'n/a')
        . ' owner=' . ($meta['owner'] !== null ? (string) $meta['owner'] : 'n/a'));
    out('  realpath=' . ($meta['realpath'] ?? 'n/a'));
}

out('');
out('[Simulation]');
$firstWritable = null;
foreach ($candidates as $label => $path) {
    if ($path !== '' && is_dir($path) && is_writable($path)) {
        $firstWritable = $label . ' => ' . $path;
        break;
    }
}
out('Primeiro candidato utilizável: ' . ($firstWritable ?? 'nenhum'));

out('');
out('[Checklist de correção para host]');
out('1) Criar diretório temporário válido (ex.: /home/USER/tmp_upload).');
out('2) Aplicar permissões para utilizador web (ex.: chown USER:USER e chmod 1733/1777).');
out('3) Definir upload_tmp_dir no php.ini/.user.ini para esse diretório.');
out('4) Definir UPLOAD_FALLBACK_TMP_DIR no .env como segurança adicional.');
out('5) Reiniciar PHP-FPM/Apache e validar novamente este script.');
out('6) Testar upload real e confirmar ausência de UPLOAD_ERR_NO_TMP_DIR no log.');

exit(0);
