<?php
declare(strict_types=1);

final class BannerMediaService
{
    private string $baseDir;
    private string $baseUrl;

    private const IMAGE_MAX_BYTES = 4 * 1024 * 1024;
    private const ALLOWED_IMAGE_MIME = ['image/jpeg','image/png','image/webp','image/gif','image/avif'];
    private const ALLOWED_IMAGE_EXT  = ['jpg','jpeg','png','webp','gif','avif'];

    public function __construct(?string $baseDir = null, ?string $baseUrl = null)
    {
        $cfg = [];
        $cfgPath = __DIR__ . '/../config/media.php';
        if (is_file($cfgPath)) $cfg = require $cfgPath;

        $dir = $baseDir ?? ($cfg['MEDIA_BASE_DIR'] ?? (__DIR__ . '/../multimedia'));
        $dir = rtrim($dir, "/\\");
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('No se pudo crear base de media: ' . $dir);
        if (!is_writable($dir)) throw new RuntimeException('Base de media no escribible: ' . $dir);

        $url = $baseUrl ?? ($cfg['MEDIA_BASE_URL'] ?? (isset($_SERVER['HTTP_HOST'])
            ? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'])
            : 'http://localhost'));

        $this->baseDir = $dir;
        $this->baseUrl = rtrim($url, '/');
    }

    /** Guarda imagen en /multimedia/banners/ prefijada con el slot (banner_top/bottom). Devuelve URL absoluta. */
    public function saveImageForSlot(array $files, string $bannerName, bool $allowEmpty = false): ?string
    {
        $file = $files['image'] ?? null;
        if (!$file) { if ($allowEmpty) return null; throw new InvalidArgumentException('No se envió archivo (image).'); }
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if ($allowEmpty) return null; throw new InvalidArgumentException('No se envió archivo (image).');
        }
        return $this->storeOne($file, 'banners', $bannerName);
    }

    private function storeOne(array $file, string $type, string $slot): string
    {
        $name = (string)($file['name'] ?? 'file');
        $err  = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($err !== UPLOAD_ERR_OK) throw new InvalidArgumentException("$name: error de carga ($err)");

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new InvalidArgumentException("$name: archivo temporal inválido");

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::IMAGE_MAX_BYTES) throw new InvalidArgumentException("$name: excede límite");

        $mime = '';
        if (class_exists('finfo')) $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($mime === '' && function_exists('mime_content_type')) $mime = (string)mime_content_type($tmp);
        if ($mime === '' && isset($file['type'])) $mime = (string)$file['type'];
        if (!in_array($mime, self::ALLOWED_IMAGE_MIME, true)) throw new InvalidArgumentException("$name: tipo no permitido ($mime)");

        $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/avif'=>'avif'];
        $ext = $extMap[$mime] ?? strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');

        $rand = bin2hex(random_bytes(3));
        $filename = $slot . '_' . date('Ymd_His') . "_{$rand}." . $ext;

        $dir = $this->baseDir . '/' . $type;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new InvalidArgumentException("No se pudo crear dir: $dir");
        if (!is_writable($dir)) throw new InvalidArgumentException("Dir no escribible: $dir");

        $dest = "{$dir}/{$filename}";
        if (!@move_uploaded_file($tmp, $dest)) if (!@rename($tmp, $dest)) throw new InvalidArgumentException("No se pudo mover $name");
        @chmod($dest, 0644);

        $publicRoot = trim(basename($this->baseDir), '/'); // "multimedia"
        return $this->baseUrl . '/' . $publicRoot . '/' . $type . '/' . $filename;
    }
}
