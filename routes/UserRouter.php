<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../enums/roles.enum.php';
#endregion

/**
 * Enrutador UserRouter (CRUD puro contra UserController)
 */
final class UserRouter
{
    private UserController $userController;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->userController = new UserController();
        $this->authMiddleware = new AuthMiddleware();
    }

    /**
     * Entrada principal
     */
    public function handleRequest(string $method, string $path, array $params): void
    {
        $path     = strtok($path, '?');
        $basePath = '/api';

        if (strpos($path, $basePath) !== 0) {
            ResponseHelper::respondWithError(['Ruta no encontrada.'], 404);
            return;
        }

        // Normalizo path relativo: /api/...
        $path = substr($path, strlen($basePath));

        // Guard global: requiere usuario autenticado y con rol válido
        $this->authMiddleware->isAuthenticated(null, null, function () {
            $allowedRoles = array_map(fn($role) => $role->value, Role::getRoles());
            $this->authMiddleware->authorize($allowedRoles, null, null, function () {});
        });

        try {
            switch (strtoupper($method)) {
                case 'GET':
                    $this->handleGet($path);
                    break;
                case 'POST':
                    // No hay create en UserController (CRUD aquí es RUD).
                    ResponseHelper::respondWithError(['Método no permitido en esta ruta.'], 405);
                    break;
                case 'PUT':
                    $this->handlePut($path, $params);
                    break;
                case 'DELETE':
                    $this->handleDelete($path);
                    break;
                default:
                    ResponseHelper::respondWithError(['Método no permitido.'], 405);
            }
        } catch (Throwable $e) {
            ResponseHelper::respondWithError(['Server error: ' . $e->getMessage()], 500);
        }
    }

    /* ==========================
       GET
       ========================== */

    private function handleGet(string $path): void
    {
        // GET /users/{id}
        if (preg_match('#^/users/(\d+)$#', $path, $m)) {
            $userId = $this->validateIntId($m[1]);

            // Solo SUPERADMIN/ADMIN/DEV
            $this->authMiddleware->requireManySpecificRoles(
                [
                    Role::SUPERADMIN->value,
                    Role::ADMIN->value,
                    Role::DEV->value
                ],
                null,
                null,
                function () use ($userId) {
                    $this->userController->getUserById($userId);
                }
            );
            return;
        }

        // GET /users
        if ($path === '/users') {
            // Todos los roles menos CLIENT listan
            $allowedRoles = array_map(fn($role) => $role->value, Role::getRoles());
            $excludeRoles = [Role::CLIENT->value];

            $this->authMiddleware->authorizeExcludingRoles(
                $allowedRoles,
                $excludeRoles,
                function () {
                    $this->userController->getAllUsers();
                }
            );
            return;
        }

        ResponseHelper::respondWithError(['Ruta no encontrada.'], 404);
    }

    /* ==========================
       PUT
       ========================== */

    private function handlePut(string $path, array $params): void
    {
        // PUT /users/{id}
        if (preg_match('#^/users/(\d+)$#', $path, $m)) {
            $userId = $this->validateIntId($m[1]);

            // Cualquier rol autenticado puede actualizar su perfil / o según tu negocio
            // Acá permitimos todos los roles del sistema
            $this->authMiddleware->requireManySpecificRoles(
                [
                    Role::SUPERADMIN->value,
                    Role::ADMIN->value,
                    Role::DEV->value,
                    Role::SELLER->value,
                    Role::SUPPORT->value,
                    Role::CLIENT->value
                ],
                null,
                null,
                function () use ($userId, $params) {
                    $this->userController->updateUser($userId, $params);
                }
            );
            return;
        }

        ResponseHelper::respondWithError(['Ruta no encontrada.'], 404);
    }

    /* ==========================
       DELETE
       ========================== */

    private function handleDelete(string $path): void
    {
        // DELETE /users/{id}
        if (preg_match('#^/users/(\d+)$#', $path, $m)) {
            $userId = $this->validateIntId($m[1]);

            // Sólo SUPERADMIN elimina
            $this->authMiddleware->requireSpecificRole(
                Role::SUPERADMIN->value,
                null,
                null,
                function () use ($userId) {
                    $this->userController->deleteUser($userId);
                }
            );
            return;
        }

        ResponseHelper::respondWithError(['Ruta no encontrada.'], 404);
    }

    /* ==========================
       Helpers
       ========================== */

    private function validateIntId(string $id): int
    {
        if (ctype_digit($id) && (int)$id > 0) {
            return (int)$id;
        }
        ResponseHelper::respondWithError(['El ID debe ser un número entero positivo.'], 400);
        // por si ResponseHelper no sale del flujo:
        throw new InvalidArgumentException('ID inválido');
    }
}
