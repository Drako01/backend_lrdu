<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../interfaces/Repository.Interface.php';
require_once __DIR__ . '/../interfaces/CategoriaRepository.Interface.php';
#endregion

final class CategoriaRepository implements CategoriaRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $conn = Conexion::getInstance()->getConnection();
        if (!$conn instanceof PDO) {
            throw new RuntimeException('No se pudo obtener la conexión PDO.');
        }
        $this->pdo = $conn;
    }

    public function findById(int $id): ?object
    {
        $st = $this->pdo->prepare('SELECT id_cat, nombre FROM categorias WHERE id_cat = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $st = $this->pdo->query('SELECT id_cat, nombre FROM categorias ORDER BY nombre ASC');
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Categoria) {
            throw new InvalidArgumentException('Entidad inválida para CategoriaRepository::save');
        }
        $st = $this->pdo->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)');
        $st->execute([':nombre' => $entity->getNombre()]);
        $entity->setIdCat((int)$this->pdo->lastInsertId());
    }

    public function update(object $entity): void
    {
        if (!$entity instanceof Categoria || $entity->getIdCat() === null) {
            throw new InvalidArgumentException('Entidad inválida o sin ID para update.');
        }
        $st = $this->pdo->prepare('UPDATE categorias SET nombre = :nombre WHERE id_cat = :id');
        $st->execute([
            ':nombre' => $entity->getNombre(),
            ':id'     => $entity->getIdCat(),
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM categorias WHERE id_cat = :id');
        $st->execute([':id' => $id]);
    }

    private function hydrate(array $row): Categoria
    {
        $c = new Categoria($row['nombre']);
        $c->setIdCat((int)$row['id_cat']);
        return $c;
    }

    public function existsByNombre(string $nombre, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = 'SELECT 1 FROM categorias WHERE nombre = :nombre AND id_cat <> :id LIMIT 1';
            $st  = $this->pdo->prepare($sql);
            $st->execute([':nombre' => $nombre, ':id' => $excludeId]);
        } else {
            $sql = 'SELECT 1 FROM categorias WHERE nombre = :nombre LIMIT 1';
            $st  = $this->pdo->prepare($sql);
            $st->execute([':nombre' => $nombre]);
        }
        return (bool)$st->fetchColumn();
    }

    public function findAllWithCounts(): array
    {
        $sql = "
                SELECT c.id_cat, c.nombre, COUNT(p.id_producto) AS productos
                FROM categorias c
                LEFT JOIN productos p ON p.id_categoria = c.id_cat
                GROUP BY c.id_cat, c.nombre
                ORDER BY c.nombre ASC
            ";
        $st = $this->pdo->query($sql);

        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id_cat'    => (int)$row['id_cat'],
                'nombre'    => (string)$row['nombre'],
                'productos' => (int)$row['productos'], // 0 si no tiene
            ];
        }
        return $out;
    }

    public function countProductsByCategory(int $id): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = :id');
        $st->execute([':id' => $id]);
        return (int)$st->fetchColumn();
    }
}
