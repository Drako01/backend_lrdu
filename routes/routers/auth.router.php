<?php
#region Imports
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../helpers/ResponseHelper.php';
require_once __DIR__ . '/../../enums/roles.enum.php';
require_once __DIR__ . '/../../helpers/verify.helper.php';
#endregion
class AuthRouter
{
    #region Declaracion de Variables Globales
    private $authController;
    #endregion
    #region Constructor
    public function __construct()
    {
        $this->authController = new AuthController();
    }


    public function handlesRoute($path)
    {
        $routes = [
            '/register',
            '/login',
            '/logout',
        ];
        return in_array($path, $routes);
    }


    public function authorizationRequest($path, $params)
    {
        switch ($path) {
            #region Autenticacion y Seguridad
            case '/register':
                $this->authController->register($params);
                break;
            case '/login':
                $this->authController->login($params);
                break;
            case '/logout':
                $this->authController->logout();
                break;
            #endregion
            default:
                ResponseHelper::respondWithError('Ruta ' . $path . ' no encontrada.', 404);
                break;
        }
    }
}
