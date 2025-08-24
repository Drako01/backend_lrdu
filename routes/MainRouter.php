<?php
#region Imports
// Controllers
require_once __DIR__ . '/../controllers/AuthController.php';
// Middlewares
require_once __DIR__ . '/../middlewares/auth.middleware.php';
// Enums
require_once __DIR__ . '/../enums/roles.enum.php';
// Validators
// Exceptions
// Helpers
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../helpers/verify.helper.php';
// Routers
require_once __DIR__ . '/routers/auth.router.php';
require_once __DIR__ . '/routers/product.router.php';
require_once __DIR__ . '/routers/category.router.php';
require_once __DIR__ . '/routers/banners.router.php';
#endregion
/**
 * Enrutador MainRouter
 * 
 * Enrutador principal para las solicitudes de
 * Autenticacion de Usuarios, tanto desde una 
 * Aplicacion Frontend como desde Postman o cualquier 
 * otro metodo para manejar solicitudes al Backend. 
 * 
 * Este enrutador maneja tambien las solicitudes
 * de Character y Server.
 * Character: Se encarga de todas las Solicitudes
 * del Usuario hacia los Personages del Juego.
 * Server: Se encarga de ver que el Servidor este
 * 100% en Linea.
 * 
 * @Drako01
 */
class MainRouter
{
    #region Declaracion de Variables Globales
    private $authRouter;
    private $productsRouter;
    private $categoryRouter;
    private $bannerRouter;
    private $authMiddleware;
    private $authController;
    #endregion

    #region Constructor
    public function __construct()
    {
        $this->authRouter       = new AuthRouter();
        $this->productsRouter   = new ProductsRouter();
        $this->categoryRouter   = new CategoryRouter();
        $this->bannerRouter     = new BannersApiRouter();
        $this->authMiddleware   = new AuthMiddleware();
        $this->authController   = new AuthController();
    }

    #endregion

    private function isPublicPath(string $method, string $path): bool
    {
        // Mapear por método para no abrir más de la cuenta
        $publicByMethod = [
            'GET' => [
                '#^/activate$#',
                '#^/reset-password-email$#',
                '#^/all-products/?$#',
                '#^/product-by-id/\d+/?$#',
                '#^/all-categories/?$#',
                '#^/category-by-id/\d+/?$#',
                '#^/get-banner/?$#',
                '#^/banners/?$#',
            ],
            'POST' => [
                // '#^/register$#',
                '#^/login$#',
                '#^/banners/?$#',
            ],
        ];

        if (!isset($publicByMethod[$method])) return false;

        foreach ($publicByMethod[$method] as $re) {
            if (preg_match($re, $path)) return true;
        }
        return false;
    }


    #region Método Principal handleRequest
    /**
     * Manejar la solicitud
     * 
     * @param string $method
     * @param string $path
     * @param array $params
     * 
     * Maneja doble control de acceso a las Rutas.
     * Por un lado usa isAuthenticated, para verificar que
     * el Usuario que esta queriendo acceder a la ruta
     * este autenticado.
     * Por otro lado usa authorize, que es similar al anterior
     * pero asegura que el Usuario autenticado tenga uno de los 
     * Roles que estan autorizados para acceder.
     */
    public function handleRequest($method, $path, $params)
    {
        $path = strtok($path, '?');
        $basePath = '/auth';

        if (strpos($path, $basePath) !== 0) {
            ResponseHelper::respondWithError('Ruta no encontrada.', 404);
            return; // 👈 cortar acá por las dudas
        }

        $path = substr($path, strlen($basePath));

        // Seguridad: solo GET sin auth para públicos
        $isPublic = $this->isPublicPath($method, $path);
        if (!$isPublic) {
            $this->authMiddleware->isAuthenticated(null, null, function () {
                $allowedRoles = array_map(fn($role) => $role->value, Role::getRoles());
                $this->authMiddleware->authorize($allowedRoles, null, null, function () {});
            });
        }
        try {
            switch ($method) {
                case 'POST':
                    return $this->handlePost($path, $params);
                case 'GET':
                    return $this->handleGet($path);
                case 'PUT':
                    return $this->handlePut($path, $params);
                    // case 'PATCH':
                    //     return $this->handlePatch($path, $params);
                case 'DELETE':
                    return $this->handleDelete($path);
                default:
                    ResponseHelper::respondWithError(['Método no permitido.'], 405);
                    return;
            }
        } catch (Exception $e) {
            ResponseHelper::respondWithError($e->getMessage(), 500);
            return;
        }
    }

    #endregion

    #region Manejar las solicitudes POST

    /**
     * Manejar las solicitudes POST
     * 
     * @param string $path
     * @param array $params
     */
    // POST
    private function handlePost($path, $params)
    {
        // Primero: AuthRouter
        if ($this->authRouter->handlesRoute($path)) {
            return $this->authRouter->authorizationRequest($path, $params);
        }
        // Luego: Productos
        if ($this->productsRouter->handlesRoute($path)) {
            return $this->productsRouter->productsRequest('POST', $path, $params);
        }
        // Luego: Categorías
        if ($this->categoryRouter->handlesRoute($path)) {
            return $this->categoryRouter->categoriesRequest('POST', $path, $params);
        }
        // Luego: Banners
        if ($this->bannerRouter->handlesRoute($path)) {
            return $this->bannerRouter->dispatch('POST', $path, $params);
        };

        ResponseHelper::respondWithError('Ruta no encontrada: ' . $path, 404);
    }


    #endregion

    #region Manejar las solicitudes GET

    /**
     * Manejar las solicitudes GET
     * 
     * @param string $path
     */
    // GET
    private function handleGet($path)
    {
        $queryString = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($queryString, $queryParams);

        if ($path === '/activate') {
            if (!isset($queryParams['token'])) {
                ResponseHelper::respondWithError(['El token es requerido.'], 400);
                return; // 👈 cortar
            }
            return $this->authController->activateUser($queryParams['token']);
        }

        if ($path === '/reset-password-email') {
            if (!isset($queryParams['email'])) {
                ResponseHelper::respondWithError(['El email es requerido.'], 400);
                return; // 👈 cortar
            }
            return $this->authController->recoveryPassword($queryParams['email']);
        }

        if ($this->productsRouter->handlesRoute($path)) {
            return $this->productsRouter->productsRequest('GET', $path, $queryParams);
        }
        if ($this->categoryRouter->handlesRoute($path)) {
            return $this->categoryRouter->categoriesRequest('GET', $path, $queryParams);
        }
        if ($this->bannerRouter->handlesRoute($path)) {
            return $this->bannerRouter->dispatch('GET', $path, $queryParams);
        }

        ResponseHelper::respondWithError(['Ruta no encontrada.'], 404);
    }


    #endregion

    /**
     * Manejar las solicitudes PUT
     *
     * @param string $path
     * @param array $params
     */
    // PUT
    private function handlePut($path, $params)
    {
        if ($this->productsRouter->handlesRoute($path)) {
            return $this->productsRouter->productsRequest('PUT', $path, $params);
        }
        if ($this->categoryRouter->handlesRoute($path)) {
            return $this->categoryRouter->categoriesRequest('PUT', $path, $params);
        }
        ResponseHelper::respondWithError('Ruta no encontrada: ' . $path, 404);
    }

    /**
     * Manejar las solicitudes DELETE
     *
     * @param string $path
     */
    // DELETE
    private function handleDelete($path)
    {
        if ($this->productsRouter->handlesRoute($path)) {
            return $this->productsRouter->productsRequest('DELETE', $path, null);
        }
        if ($this->categoryRouter->handlesRoute($path)) {
            return $this->categoryRouter->categoriesRequest('DELETE', $path, null);
        }
        ResponseHelper::respondWithError('Ruta no encontrada: ' . $path, 404);
    }
}
