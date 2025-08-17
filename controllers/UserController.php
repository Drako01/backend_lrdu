<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../enums/roles.enum.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

/**
 * Controlador para Gestionar Usuarios (CRUD)
 */
final class UserController
{
    private UserService $service;
    private array $messages;

    public function __construct(?UserService $service = null)
    {
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        $this->service  = $service instanceof UserService ? $service : new UserService();
    }

    /**
     * GET /users
     * Lista todos los usuarios (excluye ADMIN y DEV)
     */
    public function getAllUsers(): void
    {
        try {
            $users = $this->service->getAllUsers(); // array<User>

            $result = array_map(function ($u) {
                return [
                    'id'              => $u->getId(),
                    'first_name'      => $u->getFirstName(),
                    'last_name'       => $u->getLastName(),
                    'email'           => $u->getEmail(),
                    'role'            => $u->getRole()->getDisplayName(),
                    'role_value'      => $u->getRole()->value,
                    'connected_at'    => $u->getConnectedAt(),
                    'disconnected_at' => $u->getDisconnectedAt(),
                    'created_at'      => $u->getCreatedAt(),
                ];
            }, $users);

            ResponseHelper::success(array_values($result), 200, 'users');
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Server Error: ') . $e->getMessage(), 500);
        }
    }


    /**
     * GET /users/{id}
     */
    public function getUserById(int $id): void
    {
        try {
            if ($id <= 0) {
                ResponseHelper::error('El ID debe ser un entero positivo.', 400);
                return;
            }
            $user = $this->service->getUserById($id);
            if (!$user) {
                ResponseHelper::error("El usuario con id $id no existe.", 404);
                return;
            }

            $payload = [
                'id'              => $user->getId(),
                'first_name'      => $user->getFirstName(),
                'last_name'       => $user->getLastName(),
                'email'           => $user->getEmail(),
                'role'            => $user->getRole()->getDisplayName(),
                'role_value'      => $user->getRole()->value,
                'connected_at'    => $user->getConnectedAt(),
                'disconnected_at' => $user->getDisconnectedAt(),
                'created_at'      => $user->getCreatedAt(),
            ];
            ResponseHelper::success($payload, 200, 'user');
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Server Error: ') . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /users/{id}
     * $data: first_name?, last_name?, email?, password?, role?, connected_at?, disconnected_at?
     */
    public function updateUser(int $id, array $data): void
    {
        try {
            $this->service->updateUser($id, $data);
            ResponseHelper::success("El usuario con id $id fue actualizado correctamente.", 200);
        } catch (DomainException $e) {
            ResponseHelper::error($e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                ResponseHelper::error('El email ya está en uso por otro usuario.', 409);
                return;
            }
            ResponseHelper::serverError("Error del servidor: " . $e->getMessage(), 500);
        }
    }


    /**
     * DELETE /users/{id}
     */
    public function deleteUser(int $id): void
    {
        try {
            $deleted = $this->service->deleteUser($id);
            if ($deleted) {
                ResponseHelper::success("El usuario con id $id fue eliminado correctamente.", 200);
            } else {
                ResponseHelper::error("No se encontró el usuario con id $id.", 404);
            }
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Server Error: ') . $e->getMessage(), 500);
        }
    }

    /**
     * POST /users
     * Crea un usuario (solo ADMIN o SUPERADMIN).
     *
     * @param array $data       body JSON con email, password, first_name?, last_name?, role?
     * @param mixed $actorRole  rol del actor (Role|string|int) tomado del middleware/JWT
     */
    public function createUser(array $data): void
    {
        try {
            $newId = $this->service->createUser($data);
            $user  = $this->service->getUserById($newId);

            if (!$user) {
                // debería existir; fallback defensivo
                ResponseHelper::success(['id' => $newId], 201, 'user');
                return;
            }

            $payload = [
                'id'         => $user->getId(),
                'first_name' => $user->getFirstName(),
                'last_name'  => $user->getLastName(),
                'email'      => $user->getEmail(),
                'role'       => $user->getRole()->getDisplayName(),
                'role_value' => $user->getRole()->value,
                'created_at' => $user->getCreatedAt(),
            ];
            ResponseHelper::success($payload, 201, 'user');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }
}
