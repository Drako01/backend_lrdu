<?php

namespace Utils;

use PDO;
use Exception;

class TransactionManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function transactional(callable $callback)
    {
        $isTransactionActive = $this->pdo->inTransaction();

        if (!$isTransactionActive) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $callback(); // Ejecuta la lógica dentro de la transacción

            if (!$isTransactionActive) {
                $this->pdo->commit(); // Solo hace commit si la transacción no estaba activa antes
            }

            return $result;
        } catch (Exception $e) {
            if (!$isTransactionActive && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
