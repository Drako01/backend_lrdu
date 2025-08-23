<?php

declare(strict_types=1);

final class ProductMediaService
{
    private string $baseDir;
    private string $baseUrl;

    // Límites
    private const IMAGE_MAX_BYTES = 4 * 1024 * 1024;   // 4MB
    private const VIDEO_MAX_BYTES = 20 * 1024 * 1024;  // 20MB

    // Tipos permitidos (MIME y extensiones)
    private const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const ALLOWED_VIDEO_MIME = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-matroska'];

    private const ALLOWED_IMAGE_EXT  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const ALLOWED_VIDEO_EXT  = ['mp4', 'webm', 'ogv', 'mov', 'mkv'];

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
     * Guarda imágenes (field: imagenes[]) y video (field: video).
     * Devuelve URLs públicas para persistir en BD.
     *
     * @return array{images: string[], video?: string}
     */
    public function saveForProducto(array $files, string $productName, string $categoryTag): array
    {
        // Aceptamos varios alias por si el front cambia
        $images = $this->collectFilesAny($files, ['imagenes', 'imagenes[]', 'images', 'image']);
        $video  = $this->collectFileAny($files, ['video', 'video_file']);

        $productSlug  = $this->slug($productName);
        $categorySlug = $this->slug($categoryTag);

        $outImages = [];
        $errors    = [];

        // Imágenes (máx 3)
        $count = 0;
        foreach ($images as $f) {
            if ($count >= 3) break;
            try {
                $outImages[] = $this->storeOne(
                    file: $f,
                    type: 'images',
                    allowedMime: self::ALLOWED_IMAGE_MIME,
                    allowedExt: self::ALLOWED_IMAGE_EXT,
                    maxBytes: self::IMAGE_MAX_BYTES,
                    productSlug: $productSlug,
                    categorySlug: $categorySlug
                );
                $count++;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Video (opcional)
        $videoUrl = null;
        if ($video !== null && ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $videoUrl = $this->storeOne(
                    file: $video,
                    type: 'videos',
                    allowedMime: self::ALLOWED_VIDEO_MIME,
                    allowedExt: self::ALLOWED_VIDEO_EXT,
                    maxBytes: self::VIDEO_MAX_BYTES,
                    productSlug: $productSlug,
                    categorySlug: $categorySlug
                );
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            // Si preferís “parcial OK”, podés no lanzar excepción.
            throw new InvalidArgumentException('Errores de carga: ' . implode(' | ', $errors));
        }

        $res = ['images' => $outImages];
        if ($videoUrl) $res['video'] = $videoUrl;
        return $res;
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function storeOne(
        array $file,
        string $type,
        array $allowedMime,
        array $allowedExt,
        int $maxBytes,
        string $productSlug,
        string $categorySlug
    ): string {
        $name = (string)($file['name'] ?? 'archivo');

        // Errores estándar de upload
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

        // MIME robusto (fileinfo -> mime_content_type -> header del cliente)
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)($fi->file($tmp) ?: '');
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)@mime_content_type($tmp);
        } else {
            $mime = (string)($file['type'] ?? '');
        }

        // Extensión + validaciones cruzadas (MIME/EXT)
        $ext = $this->extensionFromMimeOrName($mime, $name);
        $extLower = strtolower($ext);
        if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
            throw new InvalidArgumentException("$name: tipo no permitido ($mime)");
        }
        if (!in_array($extLower, $allowedExt, true)) {
            throw new InvalidArgumentException("$name: extensión no permitida (.$extLower)");
        }

        // Nombre final: producto_categoria_fecha_hash.ext
        $stamp  = (new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Ymd_His');
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $filename = "{$productSlug}_{$categorySlug}_{$stamp}_{$suffix}.{$extLower}";

        // Directorio destino
        $dir = "{$this->baseDir}/{$type}";
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new InvalidArgumentException("No se pudo crear directorio: $dir");
        }
        if (!is_writable($dir)) {
            throw new InvalidArgumentException("El directorio no es escribible: $dir");
        }

        $dest = "{$dir}/{$filename}";

        // Mover (fallback a rename por si move_uploaded_file falla en algún hosting)
        if (!@move_uploaded_file($tmp, $dest)) {
            if (!@rename($tmp, $dest)) {
                throw new InvalidArgumentException("No se pudo mover $name");
            }
        }
        @chmod($dest, 0644);

        // URL pública: BASE_URL + /{basename(baseDir)}/{type}/{filename}
        $publicRoot = trim(basename($this->baseDir), '/'); // ej: "multimedia"
        $url = $this->baseUrl . '/' . $publicRoot . '/' . $type . '/' . $filename;

        return $url;
    }

    private function extensionFromMimeOrName(string $mime, string $name): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            'video/x-matroska' => 'mkv',
        ];
        if (isset($map[$mime])) return $map[$mime];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
        if ($ext !== '') return $ext;

        return 'bin';
    }

    /**
     * Slug a prueba de iconv ausente/fallida y caracteres raros
     */
    private function slug(string $txt): string
    {
        $converted = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt) : false;
        $safe = is_string($converted) && $converted !== '' ? $converted : $txt;
        $safe = strtolower($safe);
        $safe = preg_replace('/[^a-z0-9]+/', '-', $safe) ?? '';
        $safe = trim($safe, '-');
        return $safe !== '' ? $safe : 'item';
    }


    /** Normaliza files múltiples (imagenes[]) aceptando varios aliases */
    private function collectFilesAny(array $files, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($files[$field])) {
                return $this->collectFiles($files, $field);
            }
        }
        return [];
    }

    /** Devuelve array plano de files cuando vienen como imagenes[] */
    private function collectFiles(array $files, string $field): array
    {
        $f = $files[$field];
        if (is_array($f['name'])) {
            $out = [];
            foreach ($f['name'] as $i => $name) {
                $out[] = [
                    'name'     => (string)$name,
                    'type'     => (string)($f['type'][$i] ?? ''),
                    'tmp_name' => (string)($f['tmp_name'][$i] ?? ''),
                    'error'    => (int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int)($f['size'][$i] ?? 0),
                ];
            }
            return $out;
        }
        return [[
            'name'     => (string)($f['name'] ?? ''),
            'type'     => (string)($f['type'] ?? ''),
            'tmp_name' => (string)($f['tmp_name'] ?? ''),
            'error'    => (int)($f['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int)($f['size'] ?? 0),
        ]];
    }

    /** Toma el primer file que exista entre varios aliases */
    private function collectFileAny(array $files, array $fields): ?array
    {
        foreach ($fields as $field) {
            if (isset($files[$field])) {
                return $files[$field];
            }
        }
        return null;
    }
}
