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
    private $authMiddleware;
    private $authController;
    #endregion

    #region Constructor
    public function __construct()
    {
        $this->authRouter = new AuthRouter();
        $this->productsRouter = new ProductsRouter();
        $this->categoryRouter = new CategoryRouter();
        $this->authMiddleware = new AuthMiddleware();
        $this->authController = new AuthController();
    }

    #endregion

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
            ResponseHelper::respondWithError(
                'Ruta no encontrada.',
                404
            );
        }

        $path = substr($path, strlen($basePath));

        $excludedPaths = [
            '/register',
            '/login',
            '/activate',
            '/reset-password-email',
        ];

        $isPublic = in_array($path, $excludedPaths, true);
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
                case 'PUT':         // <-- NUEVO
                    return $this->handlePut($path, $params);
                case 'DELETE':      // <-- NUEVO
                    return $this->handleDelete($path);
                default:
                    ResponseHelper::respondWithError(['Método no permitido.'], 405);
            }
        } catch (Exception $e) {
            ResponseHelper::respondWithError($e->getMessage(), 500);
        }


        exit;
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

        // Endpoints propios de Auth que ya tenías
        if ($path === '/activate') {
            if (!isset($queryParams['token'])) {
                ResponseHelper::respondWithError(['El token es requerido.'], 400);
            }
            return $this->authController->activateUser($queryParams['token']);
        }

        if ($path === '/reset-password-email') {
            if (!isset($queryParams['email'])) {
                ResponseHelper::respondWithError(['El email es requerido.'], 400);
            }
            return $this->authController->recoveryPassword($queryParams['email']);
        }

        // ↓↓↓ Delegación a subrouters (ahora sí se ejecuta antes del 404)
        if ($this->productsRouter->handlesRoute($path)) {
            return $this->productsRouter->productsRequest('GET', $path, null);
        }
        if ($this->categoryRouter->handlesRoute($path)) {
            return $this->categoryRouter->categoriesRequest('GET', $path, null);
        }

        // Nada matcheó
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
