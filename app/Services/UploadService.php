<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use RuntimeException;

final class UploadService extends Model
{
    private const DEFAULT_MAX_SIZE = 5242880; // 5 MB
    private const ALLOWED_MIME_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeImage(array $file, string $domain = 'temp'): array
    {
        $this->validateUpload($file);

        $maxSize = (int) Config::env('UPLOAD_MAX_IMAGE_SIZE', (string) self::DEFAULT_MAX_SIZE);
        if (($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('Imagem acima do tamanho permitido.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $mime = (string) mime_content_type($tmpPath);
        $extension = self::ALLOWED_MIME_MAP[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Formato de imagem não suportado.');
        }

        $safeDomain = preg_replace('/[^a-z0-9_-]/i', '', $domain) ?: 'temp';
        $baseDirectory = dirname(__DIR__, 2) . '/storage/uploads/' . $safeDomain;
        if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0755, true) && !is_dir($baseDirectory)) {
            throw new RuntimeException('Falha ao preparar diretório de uploads.');
        }

        $fileName = bin2hex(random_bytes(20)) . '.' . $extension;
        $absolutePath = $baseDirectory . '/' . $fileName;
        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Falha ao salvar o upload.');
        }

        $thumbPath = $this->createThumbnailIfPossible($absolutePath, $safeDomain, $extension);

        return [
            'path' => 'storage/uploads/' . $safeDomain . '/' . $fileName,
            'thumbnail_path' => $thumbPath,
            'mime' => $mime,
            'size' => (int) $file['size'],
        ];
    }

    public function deleteRelativePath(string $relativePath): void
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($normalized, 'storage/uploads/')) {
            return;
        }

        $absolute = dirname(__DIR__, 2) . '/' . $normalized;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function validateUpload(array $file): void
    {
        if ($file === [] || !isset($file['error'], $file['tmp_name'])) {
            throw new RuntimeException('Nenhum ficheiro enviado.');
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erro no upload.');
        }

        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Upload inválido.');
        }
    }

    private function createThumbnailIfPossible(string $absolutePath, string $domain, string $extension): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $imageRaw = @file_get_contents($absolutePath);
        if ($imageRaw === false) {
            return null;
        }
        $source = @imagecreatefromstring($imageRaw);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = 360;
        $ratio = min($max / max(1, $width), $max / max(1, $height), 1);
        $tw = (int) max(1, floor($width * $ratio));
        $th = (int) max(1, floor($height * $ratio));
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $tw, $th, $width, $height);

        $thumbDir = dirname(__DIR__, 2) . '/storage/uploads/' . $domain . '/thumbs';
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true) && !is_dir($thumbDir)) {
            imagedestroy($source);
            imagedestroy($thumb);
            return null;
        }

        $thumbName = bin2hex(random_bytes(16)) . '.' . $extension;
        $thumbAbsolute = $thumbDir . '/' . $thumbName;
        match ($extension) {
            'jpg' => imagejpeg($thumb, $thumbAbsolute, 82),
            'png' => imagepng($thumb, $thumbAbsolute, 7),
            'webp' => imagewebp($thumb, $thumbAbsolute, 82),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($thumb);

        return is_file($thumbAbsolute) ? 'storage/uploads/' . $domain . '/thumbs/' . $thumbName : null;
    }
}
