<?php

class UploadService
{
    private string $basePath;

    public function __construct(string $basePath = __DIR__ . '/../multimedia')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function saveUploadedFile(array $file, string $username, string $characterName): ?array
    {
        if (!isset($file['tmp_name'], $file['name'], $file['type'])) {
            return null;
        }

        $mimeType = mime_content_type($file['tmp_name']);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        // Determinar tipo
        $type = $this->resolveTypeFromMime($mimeType);
        if (!$type) {
            return null; // tipo no soportado
        }

        // Construir nombre
        $timestamp = date('YmdHis');
        $safeUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '', $username);
        $safeCharacter = preg_replace('/[^a-zA-Z0-9_\-]/', '', $characterName);
        $filename = "{$safeUsername}_{$safeCharacter}_{$timestamp}.{$extension}";

        $directory = "{$this->basePath}/{$type}";

        // Crear carpeta si no existe
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $destination = "{$directory}/{$filename}";

        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'path' => str_replace(realpath(__DIR__ . '/../'), '', $destination),
                'type' => $type, // ← 'images', 'videos' o 'audios'
            ];
        }

        return null;
    }

    private function resolveTypeFromMime(string $mimeType): ?string
    {
        // error_log("Tipo MIME recibido: " . $mimeType);
        return match (true) {
            str_starts_with($mimeType, 'image/')       => 'images',
            str_starts_with($mimeType, 'video/')       => 'videos',
            str_starts_with($mimeType, 'audio/')       => 'audios',
            $mimeType === 'text/plain'                 => 'logs',
            $mimeType === 'application/octet-stream'   => 'logs',
            default => null,
        };
    }
}
