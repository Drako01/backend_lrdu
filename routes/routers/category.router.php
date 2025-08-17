<?php
#region Imports
require_once __DIR__ . '/../../controllers/CategoriaController.php';
require_once __DIR__ . '/../../helpers/ResponseHelper.php';
#endregion

class CategoryRouter
{
    #region Variables
    private CategoriaController $controller;
    #endregion

    public function __construct()
    {
        $this->controller = new CategoriaController();
    }

    /** Declara qué paths maneja este subrouter (relativos al basePath del MainRouter) */
    public function handlesRoute(string $path): bool
    {
        if ($path === '/all-categories') return true;
        if (preg_match('#^/category-by-id/(\d+)/?$#', $path)) return true;
        if ($path === '/categories') return true;
        if (preg_match('#^/categories/(\d+)/?$#', $path)) return true;
        return false;
    }


    /**
     * Maneja la request delegada desde el MainRouter
     * @param string $method HTTP method
     * @param string $path   Path relativo (ej: /categorias/5)
     * @param array|null $params Body ya parseado (si aplica)
     */
    public function categoriesRequest(string $method, string $path, ?array $params = null): void
    {
        switch ($method) {
            case 'GET':
                if ($path === '/all-categories') { $this->controller->getAll(); return; }
                if (preg_match('#^/category-by-id/(\d+)/?$#', $path, $m)) { // 👈 /?$
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                        return; // 👈 cortar
                    }
                    $this->controller->getById($id); return;
                }
                break;

            case 'POST':
                if ($path === '/categories') { $this->controller->create($params); return; }
                break;

            case 'PUT':
                if (preg_match('#^/categories/(\d+)/?$#', $path, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                        return;
                    }
                    $this->controller->update($id, $params); return;
                }
                break;

            case 'DELETE':
                if (preg_match('#^/categories/(\d+)/?$#', $path, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                        return;
                    }
                    $this->controller->delete($id); return;
                }
                break;
        }
        ResponseHelper::respondWithError('Ruta ' . $path . ' no encontrada.', 404);
    }
}
