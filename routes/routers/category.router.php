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
        // Soporta: /categorias, /categorias/{id}
        if ($path === '/categorias') return true;
        return (bool)preg_match('#^/categorias/(\d+)$#', $path);
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
                if ($path === '/categorias') {
                    $this->controller->getAll();
                    return;
                }
                if (preg_match('#^/categorias/(\d+)$#', $path, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                    }
                    $this->controller->getById($id);
                    return;
                }
                break;

            case 'POST':
                if ($path === '/categorias') {
                    $this->controller->create($params);
                    return;
                }
                break;

            case 'PUT':
                if (preg_match('#^/categorias/(\d+)$#', $path, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                    }
                    $this->controller->update($id, $params);
                    return;
                }
                break;

            case 'DELETE':
                if (preg_match('#^/categorias/(\d+)$#', $path, $m)) {
                    $id = (int)$m[1];
                    if ($id <= 0) {
                        ResponseHelper::respondWithError(['El ID debe ser un entero positivo.'], 400);
                    }
                    $this->controller->delete($id);
                    return;
                }
                break;
        }

        ResponseHelper::respondWithError('Ruta ' . $path . ' no encontrada.', 404);
    }
}
