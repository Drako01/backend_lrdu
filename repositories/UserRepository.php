<?php

declare(strict_types=1);

#region Imports
use Utils\TransactionManager;

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../enums/roles.enum.php';
#endregion

/**
 * Repositorio para Gestionar Usuarios (CRUD)
 */
final class UserRepository
{
    private PDO $pdo;
    private TransactionManager $tx;

    public function __construct()
    {
        $conn = Conexion::getInstance()->getConnection();
        if (!$conn instanceof PDO) {
            throw new RuntimeException('No se pudo obtener la conexión PDO.');
        }
        $this->pdo = $conn;
        $this->tx  = new TransactionManager($this->pdo);
    }

    /** @return ?User */
    public function getUserById(int $id)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, first_name, last_name, email, role, token, connected_at, disconnected_at, created_at
            FROM users WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateUser($row) : null;
    }

    /** @return User[] */
    public function getAllUsers(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, first_name, last_name, email, role, token, connected_at, disconnected_at, created_at
            FROM users
            ORDER BY id ASC
        ");
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $u = $this->hydrateUser($row);
            if ($u) $out[] = $u;
        }
        return $out;
    }

    /**
     * Update dinámico: sólo columnas presentes en $data
     * Campos permitidos: first_name, last_name, email, password(hash), role(value), token, connected_at, disconnected_at
     */
    public function updateUser(int $id, array $data): bool
    {
        $allowed = ['first_name', 'last_name', 'email', 'password', 'role', 'token', 'connected_at', 'disconnected_at'];
        $fields  = array_intersect_key($data, array_flip($allowed));
        if (empty($fields)) return true;

        $sets   = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            $sets[] = "$col = :$col";
            $params[":$col"] = $val;
        }

        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser(int $id): bool
    {
        return $this->tx->transactional(function () use ($id) {
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        });
    }

    /**
     * Inserta un usuario y retorna su ID.
     * Campos aceptados: first_name, last_name, email, password(hash), role, token, connected_at, disconnected_at, created_at
     *
     * - Maneja violaciones de UNIQUE(email) y lanza InvalidArgumentException de negocio.
     * - Soporta pgsql (RETURNING id) y mysql/mariadb (lastInsertId).
     *
     * @throws InvalidArgumentException si email duplicado u otra violación de restricción conocida
     * @throws RuntimeException para errores de persistencia genéricos
     */
    public function createUser(array $data): int
    {
        $allowed = ['first_name', 'last_name', 'email', 'password', 'role', 'token', 'connected_at', 'disconnected_at', 'created_at'];
        $fields  = array_intersect_key($data, array_flip($allowed));
        if (empty($fields)) {
            throw new InvalidArgumentException('No hay datos para crear el usuario.');
        }

        $columns      = array_keys($fields);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);
        $params       = [];
        foreach ($fields as $col => $val) {
            $params[':' . $col] = $val;
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'pgsql') {
                $sql = "INSERT INTO users (" . implode(',', $columns) . ")
                        VALUES (" . implode(',', $placeholders) . ")
                        RETURNING id";

                return $this->tx->transactional(function () use ($sql, $params) {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return (int)$stmt->fetchColumn();
                });
            }

            // MySQL / MariaDB
            $sql = "INSERT INTO users (" . implode(',', $columns) . ")
                    VALUES (" . implode(',', $placeholders) . ")";

            return $this->tx->transactional(function () use ($sql, $params) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return (int)$this->pdo->lastInsertId();
            });
        } catch (PDOException $e) {
            // Normalizamos violación de UNIQUE(email) a un error de negocio claro.
            // PG suele usar 23505; MySQL/Maria 23000/1062; por las dudas chequeamos el texto también.
            $code = $e->getCode();
            $msg  = mb_strtolower($e->getMessage());

            $isUnique =
                in_array($code, ['23505', '23000', '1062'], true)
                || str_contains($msg, 'unique')
                || str_contains($msg, 'duplicate')
                || str_contains($msg, 'duplicada') // por si viene localizado
                || str_contains($msg, 'duplicado');

            if ($isUnique && (str_contains($msg, 'email') || str_contains($msg, 'users_email') || str_contains($msg, 'idx'))) {
                throw new InvalidArgumentException('El email ya está registrado.');
            }

            throw new RuntimeException('Error al crear el usuario: ' . $e->getMessage());
        }
    }

    /* ==========================
        Helpers
       ========================== */

    private function hydrateUser(array $row): ?User
    {
        $role = Role::tryFrom($row['role'] ?? '') ?? Role::CLIENT;

        // ATENCIÓN: el constructor del modelo User minimal exige password en texto
        // y la hashea. Para evitar re-hashear, NO seteamos password (no hace falta para lectura).
        // Creamos con una contraseña dummy segura y *NO* la persistimos nunca.
        $dummyPwd = '********'; // no se usa para update ya que updateUser sólo manda password si viene en $data

        return new User(
            id: (int)$row['id'],
            first_name: $row['first_name'] ?? null,
            last_name: $row['last_name'] ?? null,
            password: $dummyPwd,                 // <- ver nota
            email: $row['email'] ?? null,
            role: $role,
            token: $row['token'] ?? null,
            connected_at: $row['connected_at'] ?? null,
            disconnected_at: $row['disconnected_at'] ?? null,
            created_at: $row['created_at'] ?? null,
        );
    }
}
