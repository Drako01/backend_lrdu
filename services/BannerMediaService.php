<?php

declare(strict_types=1);

final class BannerMediaService
{
    private string $baseDir;
    private string $baseUrl;

    // 4MB
    private const IMAGE_MAX_BYTES = 4 * 1024 * 1024;
    private const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_IMAGE_EXT  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(?string $baseDir = null, ?string $baseUrl = null)
    {
        $cfg = [];
        $cfgPath = __DIR__ . '/../config/media.php';
        if (is_file($cfgPath)) {
            $cfg = require $cfgPath;
        }

        // ⚙️ Usar carpeta dentro del proyecto por defecto
        $dir = $baseDir ?? ($cfg['MEDIA_BASE_DIR'] ?? (__DIR__ . '/../multimedia'));
        $dir = rtrim($dir, "/\\");
        if (!is_dir($dir)) {
            // intentar crear recursivo dentro de tu home
            if (!@mkdir($dir, 0755, true)) {
                throw new RuntimeException('No se pudo crear base de media: ' . $dir);
            }
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Base de media no escribible: ' . $dir);
        }

        $url = $baseUrl ?? ($cfg['MEDIA_BASE_URL'] ?? (isset($_SERVER['HTTP_HOST'])
            ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'))
            : 'http://localhost'));

        $this->baseDir = $dir;
        $this->baseUrl = rtrim($url, '/');
    }


    /**
     * Sube UNA sola imagen. Retorna la URL pública.
     * Acepta aliases: banner | image | imagen | file
     *
     * @return string|null  si allowEmpty=true y no vino archivo, devuelve null
     */
    public function saveForBanner(array $files, bool $allowEmpty = false): ?string
    {
        $file = $this->collectFileAny($files, ['banner', 'image', 'imagen', 'file']);
        if ($file === null) {
            if ($allowEmpty) return null;
            throw new InvalidArgumentException('No se envió archivo de imagen (campo banner/image/imagen/file).');
        }

        // Si no vino archivo (UPLOAD_ERR_NO_FILE)
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if ($allowEmpty) return null;
            throw new InvalidArgumentException('No se envió archivo de imagen.');
        }

        return $this->storeOne(
            file: $file,
            type: 'banners',
            allowedMime: self::ALLOWED_IMAGE_MIME,
            allowedExt: self::ALLOWED_IMAGE_EXT,
            maxBytes: self::IMAGE_MAX_BYTES
        );
    }

    /** Valida y mueve el archivo, retorna URL pública */
    private function storeOne(array $file, string $type, array $allowedMime, array $allowedExt, int $maxBytes): string
    {
        $name = (string)($file['name'] ?? 'file');

        $err = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($err !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE   => 'excede upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE  => 'excede MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'carga incompleta',
                UPLOAD_ERR_NO_FILE    => 'no se envió archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'sin carpeta temporal',
                UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir',
                UPLOAD_ERR_EXTENSION  => 'bloqueado por extensión',
            ];
            $msg = $map[$err] ?? "código $err";
            throw new InvalidArgumentException("$name: error de carga ($msg)");
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException("$name: archivo temporal inválido");
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $mb = number_format($maxBytes / 1024 / 1024, 0);
            throw new InvalidArgumentException("$name: excede límite de {$mb}MB");
        }

        // MIME detection robusta
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)($fi->file($tmp) ?: '');
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string)(mime_content_type($tmp) ?: '');
        }
        if ($mime === '' && isset($file['type'])) {
            $mime = (string)$file['type'];
        }

        if (!in_array($mime, $allowedMime, true)) {
            throw new InvalidArgumentException("$name: tipo no permitido ($mime)");
        }

        // Generar filename
        $ext = $this->extensionFromMimeOrName($mime, $name);
        if (!in_array($ext, $allowedExt, true)) {
            throw new InvalidArgumentException("$name: extensión no permitida ($ext)");
        }

        $rand = bin2hex(random_bytes(4));
        $filename = 'banner-' . date('Ymd-His') . "-{$rand}.{$ext}";

        // Dir destino: {baseDir}/banners/
        $dir = $this->baseDir . '/' . trim($type, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new InvalidArgumentException("No se pudo crear directorio destino: $dir");
        }
        if (!is_writable($dir)) {
            throw new InvalidArgumentException("El directorio no es escribible: $dir");
        }

        $dest = "{$dir}/{$filename}";

        if (!@move_uploaded_file($tmp, $dest)) {
            if (!@rename($tmp, $dest)) {
                throw new InvalidArgumentException("No se pudo mover $name");
            }
        }
        @chmod($dest, 0644);

        // URL pública: BASE_URL + /{basename(baseDir)}/{type}/{filename}
        $publicRoot = trim(basename($this->baseDir), '/'); // ej: "multimedia"
        return $this->baseUrl . '/' . $publicRoot . '/' . $type . '/' . $filename;
    }

    private function extensionFromMimeOrName(string $mime, string $name): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (isset($map[$mime])) return $map[$mime];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $ext !== '' ? $ext : 'jpg';
    }

    private function collectFileAny(array $files, array $fields): ?array
    {
        foreach ($fields as $field) {
            if (isset($files[$field])) {
                $f = $files[$field];
                // normalizo por si viene en formato simple
                if (!is_array($f)) return null;
                if (is_array($f['name'] ?? null)) {
                    // si mandaron múltiples, tomo el primero
                    $firstIdx = array_key_first($f['name']);
                    return [
                        'name'     => (string)($f['name'][$firstIdx] ?? ''),
                        'type'     => (string)($f['type'][$firstIdx] ?? ''),
                        'tmp_name' => (string)($f['tmp_name'][$firstIdx] ?? ''),
                        'error'    => (int)($f['error'][$firstIdx] ?? UPLOAD_ERR_NO_FILE),
                        'size'     => (int)($f['size'][$firstIdx] ?? 0),
                    ];
                }
                return [
                    'name'     => (string)($f['name'] ?? ''),
                    'type'     => (string)($f['type'] ?? ''),
                    'tmp_name' => (string)($f['tmp_name'] ?? ''),
                    'error'    => (int)($f['error'] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int)($f['size'] ?? 0),
                ];
            }
        }
        return null;
    }
}
