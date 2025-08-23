<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../../controllers/BannerController.php';
require_once __DIR__ . '/../../helpers/ResponseHelper.php';
#endregion

final class BannersRouter
{
    #region Variables
    private BannerController $controller;
    #endregion

    public function __construct()
    {
        $this->controller = new BannerController();
    }

    /**
     * Indica si este subrouter maneja el path dado (relativo al basePath del MainRouter).
     */
    public function handlesRoute(string $path): bool
    {
        // Normalizo trailing slash (aceptamos con o sin)
        $p = rtrim($path, '/');
        if ($p === '') { $p = '/'; }

        // Listado
        if ($p === '/banners' || $p === '/all-banners') return true;

        // Detalle por ID (dos variantes)
        if (preg_match('#^/banners/\d+$#', $p)) return true;
        if (preg_match('#^/banner-by-id/\d+$#', $p)) return true;

        return false;
    }

    /**
     * Maneja la request delegada desde el MainRouter.
     * @param string     $method  HTTP method
     * @param string     $path    Path relativo
     * @param array|null $params  Body ya parseado (si aplica)
     */
    public function bannersRequest(string $method, string $path, ?array $params = null): void
    {
        $method = strtoupper($method);
        $p = rtrim($path, '/');
        if ($p === '') { $p = '/'; }

        // Ruteo por método
        switch ($method) {
            case 'GET':
                // Listado
                if ($p === '/banners' || $p === '/all-banners') {
                    $this->controller->list();
                    return;
                }

                // Detalle por ID (acepta /banners/{id} y /banner-by-id/{id})
                if (preg_match('#^/banners/(\d+)$#', $p, $m) || preg_match('#^/banner-by-id/(\d+)$#', $p, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                        return;
                    }
                    $this->controller->getById($id);
                    return;
                }
                break;

            case 'POST':
                // Crear
                if ($p === '/banners') {
                    $this->controller->create($params);
                    return;
                }
                // Si llegó a /banner-by-id/{id} con POST => no permitido
                if (preg_match('#^/(?:banners|banner-by-id)/\d+$#', $p)) {
                    ResponseHelper::respondWithError(['Método no permitido en esta ruta.'], 405);
                    return;
                }
                break;

            case 'PUT':
            case 'PATCH':
                // Actualizar por ID
                if (preg_match('#^/banners/(\d+)$#', $p, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                        return;
                    }
                    $this->controller->update($id, $params);
                    return;
                }
                // Variante legacy no soporta update: /banner-by-id/{id} -> 405
                if (preg_match('#^/banner-by-id/\d+$#', $p)) {
                    ResponseHelper::respondWithError(['Usá /banners/{id} para actualizar.'], 405);
                    return;
                }
                break;

            case 'DELETE':
                // Eliminar por ID
                if (preg_match('#^/banners/(\d+)$#', $p, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                        return;
                    }
                    $this->controller->delete($id);
                    return;
                }
                // Variante legacy no soporta delete: /banner-by-id/{id} -> 405
                if (preg_match('#^/banner-by-id/\d+$#', $p)) {
                    ResponseHelper::respondWithError(['Usá /banners/{id} para eliminar.'], 405);
                    return;
                }
                break;
        }

        // Si el path lo manejamos pero el método no corresponde → 405
        if ($this->handlesRoute($path)) {
            ResponseHelper::respondWithError(['Método no permitido.'], 405);
            return;
        }

        // No matcheó ninguna ruta
        ResponseHelper::respondWithError('Ruta ' . $path . ' no encontrada.', 404);
    }
}
