<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

final class Conexion
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
        try {
            $config  = include __DIR__ . '/validate.php';

            $server  = $config['DB_SERVER']   ?? '127.0.0.1';
            $port    = (string)($config['DB_PORT'] ?? '3306');
            $dbname  = $config['DB_NAME']     ?? 'lrdu';
            $user    = $config['DB_USER']     ?? 'root';
            $pass    = $config['DB_PASSWORD'] ?? '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$server};port={$port};dbname={$dbname};charset={$charset}";

            $this->pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES'",
                ]
            );
        } catch (PDOException $e) {
            ResponseHelper::serverError('Error de conexión a la base de datos: ' . $e->getMessage(), 500);
            exit;
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            throw new RuntimeException('Conexión no inicializada.');
        }
        return $this->pdo;
    }

    // Opcional: verificación rápida de conexión
    public function ping(): bool
    {
        try {
            $this->pdo?->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // Dentro de la clase Conexion (la que dejé lean & mean)
    public static function setDatabaseType(): void
    {
        // No-op: mantenemos compat y aseguramos instancia creada
        self::getInstance();
    }

    public static function getDbTypeStatic(): string
    {
        // Para cualquier check legacy que tengas dando vueltas
        return 'mysql';
    }

    public static function getDescriptionDbType(): string
    {
        return 'Workbench MySQL';
    }
}
