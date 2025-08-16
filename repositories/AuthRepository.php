<?php

declare(strict_types=1);

#region Imports
use Utils\TransactionManager;

require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../exceptions/DatabaseUpdateException.php';
require_once __DIR__ . '/../utils/TransactionManager.php';
require_once __DIR__ . '/../enums/roles.enum.php';
#endregion

final class AuthRepository
{
    private PDO $pdo;
    private TransactionManager $transactionManager;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = Conexion::getInstance()->getConnection();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('No se pudo obtener la conexión PDO.');
        }
        $this->pdo = $pdo;
        $this->transactionManager = new TransactionManager($this->pdo);
    }

    /* ==========================
        Queries principales
       ========================== */

    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Alta de usuario (solo campos del modelo minimal)
     */
    public function registerUser(
        string $first_name,
        string $last_name,
        string $password,  // hash
        string $email,
        Role $role,
        ?string $token = null
    ): ?array {
        return $this->transactionManager->transactional(function () use (
            $first_name,
            $last_name,
            $password,
            $email,
            $role,
            $token
        ) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (first_name, last_name, password, email, role, token, created_at)
                    VALUES (:first_name, :last_name, :password, :email, :role, :token, NOW())
                ");
                $stmt->execute([
                    ':first_name' => $first_name,
                    ':last_name'  => $last_name,
                    ':password'   => $password,
                    ':email'      => $email,
                    ':role'       => $role->value, // guardamos el value del enum (p.ej. 'ADMIN_ROLE')
                    ':token'      => $token,
                ]);

                return $this->getUserByEmail($email);
            } catch (Throwable $e) {
                throw new DatabaseUpdateException("Error al registrar usuario: " . $e->getMessage());
            }
        });
    }

    public function deleteUserById(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /* ==========================
        Secundarios
       ========================== */

    public function isEmailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function updateUserToken(string $email, ?string $token)
    {
        $stmt = $this->pdo->prepare("UPDATE users SET token = :token WHERE email = :email");
        if ($token === null) {
            $stmt->bindValue(':token', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        }
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $this->getUserByEmail($email);
    }


    public function getUserByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateUserPassword(int $userId, string $password): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        return $stmt->execute([':password' => $password, ':id' => $userId]);
    }

    public function getTokenByUserId(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT token FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        /** @var string|null $token */
        $token = $stmt->fetchColumn();
        return $token ?: null;
    }
}
