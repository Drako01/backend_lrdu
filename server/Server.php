<?php
#region Imports
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../routes/UserRouter.php';
require_once __DIR__ . '/../routes/MainRouter.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

class Server
{
    private array $routers = [];

    public function __construct()
    {
        $this->initializeAutoloader();
        $this->handleCORS();
        $this->initializeRouters();
        date_default_timezone_set('America/Argentina/Buenos_Aires');
    }

    private function initializeAutoloader()
    {
        require_once __DIR__ . '/../helpers/autoloader.helper.php';
        handleAutoloader();
    }

    private function handleCORS()
    {
        require_once __DIR__ . '/../helpers/cors.helper.php';
        handleCORS();
    }

    private function initializeRouters()
    {
        $this->routers = [
            '/api' => new UserRouter(),
            '/auth' => new MainRouter(),
        ];
    }

    public function handleRequest()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = $_SERVER['REQUEST_URI'];
            $params = json_decode(file_get_contents('php://input'), true) ?? [];

            foreach ($this->routers as $basePath => $router) {
                if (strpos($path, $basePath) === 0) {
                    $router->handleRequest($method, $path, $params);
                    return;
                }
            }

            ResponseHelper::serverError(['status' => 'Error', 'message' => 'Ruta no encontrada.'], 404);
        } catch (\Exception $e) {
            ResponseHelper::serverError(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
