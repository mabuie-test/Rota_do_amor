<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use RuntimeException;

final class UploadService extends Model
{
    /**
     * Política base de ciclo de vida de media:
     * - Guardar ficheiros apenas em public/storage/uploads.
     * - Em falha de validação/processamento, nada é persistido.
     * - Em falha após persistência temporária, o chamador deve executar deleteImageBundle (rollback físico).
     * - Ficheiros ligados a entidades soft-deleted permanecem até purge administrativo.
     */
    private const DEFAULT_MAX_SIZE = 5242880; // 5 MB
    private const DEFAULT_MAX_WIDTH = 6000;
    private const DEFAULT_MAX_HEIGHT = 6000;
    private const DEFAULT_MAX_MEGAPIXELS = 24;
    private const ALLOWED_MIME_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeImage(array $file, string $domain = 'temp'): array
    {
        $safeDomain = $this->normalizeDomain($domain);
        $this->validateUpload($file, $safeDomain);

        $maxSize = $this->effectiveMaxSize();
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('Ficheiro acima do limite permitido para upload.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if (!is_readable($tmpPath)) {
            throw new RuntimeException('Upload corrompido ou indisponível no diretório temporário.');
        }

        $mime = (string) @mime_content_type($tmpPath);
        $extension = self::ALLOWED_MIME_MAP[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Formato inválido. Apenas JPEG, PNG e WEBP são permitidos.');
        }

        $this->assertImageGeometry($tmpPath);

        $filePrefix = str_replace(['/', '\\'], '_', $safeDomain);
        $baseDirectory = dirname(__DIR__, 2) . '/public/storage/uploads/' . $safeDomain;
        $this->ensureWritableDirectory($baseDirectory);

        $fileName = $filePrefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
        $absolutePath = $baseDirectory . '/' . $fileName;
        if (!$this->moveUploadedFile($tmpPath, $absolutePath)) {
            $this->logUploadIssue('move_uploaded_file_failed', $this->diagnosticsContext($safeDomain, (int) ($file['error'] ?? UPLOAD_ERR_OK), $tmpPath, $absolutePath));
            throw new RuntimeException('Falha ao mover ficheiro para o diretório final de uploads.');
        }

        try {
            $thumbPath = $this->createThumbnailIfPossible($absolutePath, $safeDomain, $extension);
        } catch (RuntimeException $exception) {
            $this->logUploadIssue('thumbnail_generation_failed', ['domain' => $safeDomain, 'path' => $absolutePath, 'error' => $exception->getMessage()]);
            if (@unlink($absolutePath) === false && is_file($absolutePath)) {
                $this->logUploadIssue('rollback_main_file_failed', ['domain' => $safeDomain, 'path' => $absolutePath]);
            }
            throw $exception;
        }

        return [
            'path' => 'storage/uploads/' . $safeDomain . '/' . $fileName,
            'thumbnail_path' => $thumbPath,
            'mime' => $mime,
            'size' => (int) $file['size'],
        ];
    }

    public function storeManyImages(array $files, string $domain, int $maxFiles = 4): array
    {
        $normalized = $this->normalizeFilesArray($files);
        if ($normalized === []) {
            return [];
        }

        if (count($normalized) > $maxFiles) {
            throw new RuntimeException('Quantidade de imagens acima do permitido por operação.');
        }

        $stored = [];
        try {
            foreach ($normalized as $index => $file) {
                try {
                    $stored[] = $this->storeImage($file, $domain);
                } catch (RuntimeException $exception) {
                    throw new RuntimeException(sprintf('Falha no upload do ficheiro #%d: %s', $index + 1, $exception->getMessage()));
                }
            }
        } catch (RuntimeException $exception) {
            foreach ($stored as $item) {
                $this->logUploadIssue('multi_upload_rollback', ['domain' => $domain, 'path' => $item['path'] ?? null, 'thumbnail_path' => $item['thumbnail_path'] ?? null]);
                $this->deleteImageBundle($item);
            }
            throw $exception;
        }

        return $stored;
    }

    public function deleteImageBundle(array $file): void
    {
        if (!empty($file['path'])) {
            $this->deleteRelativePath((string) $file['path']);
        }

        if (!empty($file['thumbnail_path'])) {
            $this->deleteRelativePath((string) $file['thumbnail_path']);
        }
    }

    public function deleteRelativePath(string $relativePath): void
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($normalized, 'storage/uploads/')) {
            return;
        }

        $absolute = dirname(__DIR__, 2) . '/public/' . $normalized;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function effectiveMaxSize(): int
    {
        $configuredMax = (int) Config::env('UPLOAD_MAX_IMAGE_SIZE', (string) self::DEFAULT_MAX_SIZE);
        $iniUploadMax = $this->iniSizeToBytes((string) ini_get('upload_max_filesize'));
        $iniPostMax = $this->iniSizeToBytes((string) ini_get('post_max_size'));

        $candidates = array_filter([$configuredMax, $iniUploadMax, $iniPostMax], static fn(int $value): bool => $value > 0);
        if ($candidates === []) {
            return self::DEFAULT_MAX_SIZE;
        }

        return (int) min($candidates);
    }

    private function iniSizeToBytes(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $last = strtolower(substr($trimmed, -1));
        $number = (float) $trimmed;
        return match ($last) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }

    private function normalizeDomain(string $domain): string
    {
        return preg_replace('/[^a-z0-9_\/-]/i', '', str_replace('..', '', $domain)) ?: 'temp';
    }

    private function ensureWritableDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->logUploadIssue('mkdir_failed', ['directory' => $directory]);
            throw new RuntimeException('Falha ao preparar diretório de uploads.');
        }

        if (!is_writable($directory)) {
            $this->logUploadIssue('directory_not_writable', ['directory' => $directory]);
            throw new RuntimeException('Diretório de uploads sem permissão de escrita.');
        }
    }

    private function moveUploadedFile(string $tmpPath, string $destination): bool
    {
        if (is_uploaded_file($tmpPath)) {
            return @move_uploaded_file($tmpPath, $destination);
        }

        return false;
    }

    private function validateUpload(array $file, string $domain = 'temp'): void
    {
        if ($file === [] || !isset($file['error'], $file['tmp_name'])) {
            $this->logUploadIssue('invalid_upload_payload', $this->diagnosticsContext($domain, -1, (string) ($file['tmp_name'] ?? ''), null));
            throw new RuntimeException('Nenhum ficheiro enviado.');
        }

        $errorCode = (int) $file['error'];
        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->logUploadIssue('upload_php_error', $this->diagnosticsContext($domain, $errorCode, (string) ($file['tmp_name'] ?? ''), null));
        }
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Nenhum ficheiro enviado.');
        }

        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Ficheiro acima do limite permitido para upload.');
        }

        if ($errorCode === UPLOAD_ERR_PARTIAL) {
            throw new RuntimeException('Upload corrompido: envio parcial do ficheiro.');
        }

        if ($errorCode === UPLOAD_ERR_NO_TMP_DIR) {
            $this->logUploadIssue('upload_tmp_dir_unavailable', $this->diagnosticsContext($domain, $errorCode, (string) ($file['tmp_name'] ?? ''), null));
            throw new RuntimeException('Falha no upload: diretório temporário ausente no servidor.');
        }

        if ($errorCode === UPLOAD_ERR_CANT_WRITE) {
            throw new RuntimeException('Falha no upload: sem permissão para escrever em disco.');
        }

        if ($errorCode === UPLOAD_ERR_EXTENSION) {
            throw new RuntimeException('Upload interrompido por extensão de segurança do servidor.');
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha inesperada no upload.');
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            $this->logUploadIssue('upload_tmp_name_untrusted', $this->diagnosticsContext($domain, $errorCode, (string) $file['tmp_name'], null));
            throw new RuntimeException('Upload inválido ou não confiável (tmp_name inválido).');
        }

        $this->logUploadIssue('upload_validated', $this->diagnosticsContext($domain, $errorCode, (string) $file['tmp_name'], null));
    }

    private function normalizeFilesArray(array $files): array
    {
        if (!isset($files['name'])) {
            if ($files === []) {
                return [];
            }

            return [(array) $files];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $entry = [
                'name' => $files['name'][$i] ?? null,
                'type' => $files['type'][$i] ?? null,
                'tmp_name' => $files['tmp_name'][$i] ?? null,
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => (int) ($files['size'][$i] ?? 0),
            ];

            if ((int) $entry['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    private function createThumbnailIfPossible(string $absolutePath, string $domain, string $extension): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $imageRaw = @file_get_contents($absolutePath);
        if ($imageRaw === false) {
            throw new RuntimeException('Falha ao gerar thumbnail: ficheiro temporário ilegível.');
        }

        $source = @imagecreatefromstring($imageRaw);
        if ($source === false) {
            throw new RuntimeException('Falha ao gerar thumbnail: imagem inválida ou corrompida.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = 360;
        $ratio = min($max / max(1, $width), $max / max(1, $height), 1);
        $tw = (int) max(1, floor($width * $ratio));
        $th = (int) max(1, floor($height * $ratio));
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $tw, $th, $width, $height);

        $thumbDir = dirname(__DIR__, 2) . '/public/storage/uploads/' . $domain . '/thumbs';
        $this->ensureWritableDirectory($thumbDir);

        $thumbName = bin2hex(random_bytes(16)) . '.' . $extension;
        $thumbAbsolute = $thumbDir . '/' . $thumbName;
        $ok = match ($extension) {
            'jpg' => imagejpeg($thumb, $thumbAbsolute, 82),
            'png' => imagepng($thumb, $thumbAbsolute, 7),
            'webp' => imagewebp($thumb, $thumbAbsolute, 82),
            default => false,
        };

        imagedestroy($source);
        imagedestroy($thumb);

        if (!$ok || !is_file($thumbAbsolute)) {
            $this->logUploadIssue('thumbnail_write_failed', ['path' => $thumbAbsolute, 'extension' => $extension, 'domain' => $domain]);
            throw new RuntimeException('Falha ao gerar thumbnail.');
        }

        return 'storage/uploads/' . $domain . '/thumbs/' . $thumbName;
    }

    private function logUploadIssue(string $event, array $context = []): void
    {
        $payload = $context;
        $payload['event'] = $event;
        $payload['service'] = 'UploadService';
        error_log('[upload.issue] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function diagnosticsContext(string $domain, int $errorCode, ?string $tmpPath, ?string $destination): array
    {
        $uploadTmpDir = (string) ini_get('upload_tmp_dir');
        $sysTempDir = (string) sys_get_temp_dir();
        $iniTemp = $uploadTmpDir !== '' ? $uploadTmpDir : $sysTempDir;

        return [
            'domain' => $domain,
            'upload_error_code' => $errorCode,
            'upload_error_label' => $this->uploadErrorLabel($errorCode),
            'tmp_name' => $tmpPath,
            'tmp_name_readable' => $tmpPath !== null && $tmpPath !== '' ? is_readable($tmpPath) : false,
            'tmp_name_exists' => $tmpPath !== null && $tmpPath !== '' ? file_exists($tmpPath) : false,
            'upload_tmp_dir' => $uploadTmpDir,
            'ini_upload_tmp_dir' => $uploadTmpDir,
            'sys_temp_dir' => $sysTempDir,
            'selected_tmp_dir' => $iniTemp,
            'selected_tmp_dir_exists' => $iniTemp !== '' ? is_dir($iniTemp) : false,
            'selected_tmp_dir_writable' => $iniTemp !== '' ? is_writable($iniTemp) : false,
            'destination' => $destination,
            'destination_dir' => $destination !== null ? dirname($destination) : null,
            'destination_dir_exists' => $destination !== null ? is_dir(dirname($destination)) : false,
            'destination_dir_writable' => $destination !== null ? is_writable(dirname($destination)) : false,
            'open_basedir' => (string) ini_get('open_basedir'),
        ];
    }

    private function uploadErrorLabel(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
            UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
            UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
            UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
            UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
            UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
            UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
            UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
            default => 'UNKNOWN',
        };
    }

    private function assertImageGeometry(string $path): void
    {
        $size = @getimagesize($path);
        if (!is_array($size)) {
            $this->logUploadIssue('geometry_read_failed', ['path' => $path]);
            throw new RuntimeException('Imagem inválida ou corrompida.');
        }

        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);
        if ($width <= 0 || $height <= 0) {
            $this->logUploadIssue('geometry_invalid', ['path' => $path, 'width' => $width, 'height' => $height]);
            throw new RuntimeException('Imagem inválida: dimensões não reconhecidas.');
        }

        $maxWidth = max(1280, (int) Config::env('UPLOAD_MAX_IMAGE_WIDTH', (string) self::DEFAULT_MAX_WIDTH));
        $maxHeight = max(1280, (int) Config::env('UPLOAD_MAX_IMAGE_HEIGHT', (string) self::DEFAULT_MAX_HEIGHT));
        $maxMegaPixels = max(4, (int) Config::env('UPLOAD_MAX_IMAGE_MEGAPIXELS', (string) self::DEFAULT_MAX_MEGAPIXELS));
        $maxPixels = $maxMegaPixels * 1_000_000;

        if ($width > $maxWidth) {
            $this->logUploadIssue('geometry_width_exceeded', ['path' => $path, 'width' => $width, 'max_width' => $maxWidth]);
            throw new RuntimeException(sprintf('Imagem demasiado larga. Máximo permitido: %d px.', $maxWidth));
        }

        if ($height > $maxHeight) {
            $this->logUploadIssue('geometry_height_exceeded', ['path' => $path, 'height' => $height, 'max_height' => $maxHeight]);
            throw new RuntimeException(sprintf('Imagem demasiado alta. Máximo permitido: %d px.', $maxHeight));
        }

        $pixels = $width * $height;
        if ($pixels > $maxPixels) {
            $this->logUploadIssue('geometry_megapixel_exceeded', [
                'path' => $path,
                'width' => $width,
                'height' => $height,
                'pixels' => $pixels,
                'max_pixels' => $maxPixels,
            ]);
            throw new RuntimeException(sprintf('Imagem com resolução excessiva. Máximo permitido: %d MP.', $maxMegaPixels));
        }
    }
}
