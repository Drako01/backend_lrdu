<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../enums/roles.enum.php';
#endregion

/**
 * Servicio para Gestionar Usuarios (CRUD)
 */
final class UserService
{
    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    /** @return ?User */
    public function getUserById(int $id)
    {
        return $this->repository->getUserById($id);
    }

    /** @return User[] */
    public function getAllUsers(): array
    {
        return $this->repository->getAllUsers();
    }

    /**
     * Update parcial. Sólo se actualizan campos presentes.
     * Campos permitidos: first_name, last_name, email, password, role, connected_at, disconnected_at
     */
    public function updateUser(int $id, array $data): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException("ID de usuario inválido.");
        }

        $user = $this->repository->getUserById($id);
        if (!$user) {
            throw new InvalidArgumentException("Usuario no encontrado.");
        }

        $update = [];

        // first_name / last_name (simples)
        if (isset($data['first_name'])) {
            $val = trim((string)$data['first_name']);
            if ($val === '' || mb_strlen($val) > 50) {
                throw new InvalidArgumentException('first_name inválido.');
            }
            $update['first_name'] = $val;
        }
        if (isset($data['last_name'])) {
            $val = trim((string)$data['last_name']);
            if ($val === '' || mb_strlen($val) > 50) {
                throw new InvalidArgumentException('last_name inválido.');
            }
            $update['last_name'] = $val;
        }

        // email
        if (isset($data['email'])) {
            $email = trim((string)$data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Formato de email inválido.');
            }
            $update['email'] = $email;
        }

        // password (hash sólo si viene)
        if (isset($data['password'])) {
            $pwd = (string)$data['password'];
            if (strlen($pwd) < 8) {
                throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
            }
            $update['password'] = password_hash($pwd, PASSWORD_BCRYPT);
        }

        // role (acepta value, nombre enum, display number o display name)
        if (isset($data['role'])) {
            $roleInput = $data['role'];
            $roleEnum  = $this->resolveRole($roleInput);
            if (!$roleEnum) {
                throw new InvalidArgumentException('Rol inválido.');
            }
            $update['role'] = $roleEnum->value;
        }

        // timestamps opcionales
        if (isset($data['connected_at'])) {
            $update['connected_at'] = (string)$data['connected_at']; // validar formato si querés
        }
        if (isset($data['disconnected_at'])) {
            $update['disconnected_at'] = (string)$data['disconnected_at'];
        }

        if (empty($update)) {
            // Nada por actualizar -> OK
            return;
        }

        $this->repository->updateUser($id, $update);
    }

    public function deleteUser(int $id): bool
    {
        if ($id <= 0) return false;
        return $this->repository->deleteUser($id);
    }

    /** Helper: Resuelve cualquier input a Role enum */
    private function resolveRole(mixed $input): ?Role
    {
        // numérico → display number
        if (is_int($input) || (is_string($input) && ctype_digit($input))) {
            return Role::fromDisplayNumber((int)$input);
        }

        if (!is_string($input)) return null;
        $text = trim($input);

        // Exact value (ADMIN_ROLE, CLIENT_ROLE, ...)
        if (Role::isVerifyRole($text)) {
            return Role::tryFrom($text);
        }

        // Nombre del case (ADMIN, CLIENT, ...)
        $byName = Role::tryFromName(strtoupper($text));
        if ($byName) return $byName;

        // Display name (Administrador, Cliente, ...)
        foreach (Role::getRoles() as $r) {
            if (mb_strtolower($r->getDisplayName()) === mb_strtolower($text)) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Crea un usuario nuevo.
     * Reglas:
     * - Solo ADMIN o SUPERADMIN pueden crear.
     * - ADMIN no puede crear ADMIN ni SUPERADMIN.
     *
     * $data requiere:
     *  - email (string, válido)
     *  - password (string, >=8)
     *  - first_name? / last_name? (válidos si vienen)
     *  - role? (acepta value/name/displayName/number). Default CLIENT.
     *
     * @param array $data
     * @param mixed $actorRole  (Role|string|int) desde JWT/sesión
     * @return int id del nuevo usuario
     * @throws InvalidArgumentException
     * @throws RuntimeException (cuando el repo informa un problema genérico)
     */
    public function createUser(array $data): int
    {
        // 2) Validar credenciales mínimas
        $email = isset($data['email']) ? trim((string)$data['email']) : '';
        $pwd   = isset($data['password']) ? (string)$data['password'] : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Formato de email inválido.');
        }
        if (strlen($pwd) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }

        // 3) Validar nombres si vinieron
        if (isset($data['first_name']) && !User::validateName((string)$data['first_name'])) {
            throw new InvalidArgumentException('first_name inválido.');
        }
        if (isset($data['last_name']) && !User::validateName((string)$data['last_name'])) {
            throw new InvalidArgumentException('last_name inválido.');
        }

        // 4) Rol destino (default CLIENT) + política de escalamiento
        $targetRole = isset($data['role']) ? $this->resolveRole($data['role']) : Role::CLIENT;
        if (!$targetRole) {
            throw new InvalidArgumentException('Rol inválido.');
        }

        // 5) Preparar payload para el repo (hash en capa de negocio, OK)
        $insert = [
            'email'           => $email,
            'password'        => password_hash($pwd, PASSWORD_BCRYPT),
            'first_name'      => isset($data['first_name']) ? trim((string)$data['first_name']) : null,
            'last_name'       => isset($data['last_name']) ? trim((string)$data['last_name']) : null,
            'role'            => $targetRole->value,
            'token'           => null,
            'connected_at'    => null,
            'disconnected_at' => null,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        // 6) Persistencia delegada 100% al repo
        //    El repo:
        //      - Inserta
        //      - Detecta UNIQUE(email)
        //      - Retorna id
        return $this->repository->createUser($insert);
    }
}
