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

        return [
            'path' => 'storage/uploads/' . $safeDomain . '/' . $fileName,
            'mime' => $mime,
            'size' => (int) $file['size'],
        ];
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
}
