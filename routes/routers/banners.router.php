<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../../controllers/BannerController.php';
require_once __DIR__ . '/../../helpers/ResponseHelper.php';
#endregion

final class BannersApiRouter
{
    private ?BannerController $controller = null;
    private function c(): BannerController { return $this->controller ??= new BannerController(); }

    public function handlesRoute(string $path): bool
    {
        $p = rtrim($path, '/'); if ($p==='') $p='/';
        // Endpoints oficiales
        if ($p === '/get-banner' || $p === '/update-banner') return true;
        // Aliases compat:
        if ($p === '/banners') return true;
        if (preg_match('#^/banners/(banner_top|banner_bottom)$#', $p)) return true;
        return false;
    }

    public function dispatch(string $method, string $path, ?array $params = null): void
    {
        $method = strtoupper($method);
        $p = rtrim($path, '/'); if ($p==='') $p='/';

        // ---- GETs
        if ($p === '/get-banner' && $method === 'GET') {
            $this->c()->apiGetBanner(); return;
        }
        // alias: GET /banners  -> lista ambos (o respeta ?slot=)
        if ($p === '/banners' && $method === 'GET') {
            $this->c()->apiGetBanner(); return;
        }
        // alias: GET /banners/{slot}
        if (preg_match('#^/banners/(banner_top|banner_bottom)$#', $p, $m) && $method === 'GET') {
            $_GET['slot'] = $m[1];
            $this->c()->apiGetBanner(); return;
        }

        // ---- POST (oficial)
        if ($p === '/update-banner' && $method === 'POST') {
            $this->c()->apiUpdateBanner(); return;
        }
        // (opcional) alias: POST /banners
        if ($p === '/banners' && $method === 'POST') {
            $this->c()->apiUpdateBanner(); return;
        }

        if ($this->handlesRoute($path)) {
            ResponseHelper::respondWithError(['Método no permitido.'], 405); return;
        }
        ResponseHelper::respondWithError('Ruta ' . $path . ' no encontrada.', 404);
    }
}

