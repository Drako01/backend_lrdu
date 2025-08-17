<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../interfaces/Repository.Interface.php';
require_once __DIR__ . '/../interfaces/ProductoRepository.Interface.php';
#endregion

final class ProductoRepository implements ProductoRepositoryInterface
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
        $st = $this->pdo->prepare('SELECT * FROM productos WHERE id_producto = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $st = $this->pdo->query('SELECT * FROM productos ORDER BY fecha_creacion DESC, id_producto DESC');
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Producto) {
            throw new InvalidArgumentException('Entidad inválida para ProductoRepository::save');
        }

        $sql = 'INSERT INTO productos
            (nombre, descripcion, id_categoria, stock, precio, marca, modelo, caracteristicas, codigo_interno, imagen_principal, favorito, activo)
            VALUES
            (:nombre, :descripcion, :id_categoria, :stock, :precio, :marca, :modelo, :caracteristicas, :codigo_interno, :imagen_principal, :favorito, :activo)';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':nombre'            => $entity->getNombre(),
            ':descripcion'       => $entity->getDescripcion(),
            ':id_categoria'      => $entity->getIdCategoria(),
            ':stock'             => $entity->getStock(),
            ':precio'            => $entity->getPrecio(),
            ':marca'             => $entity->getMarca(),
            ':modelo'            => $entity->getModelo(),
            ':caracteristicas'   => $entity->getCaracteristicas(),
            ':codigo_interno'    => $entity->getCodigoInterno(),
            ':imagen_principal'  => $entity->getImagenesAsJson(),
            ':favorito'          => $entity->isFavorito() ? 1 : 0,
            ':activo'            => $entity->isActivo() ? 1 : 0,
        ]);

        $entity->setIdProducto((int)$this->pdo->lastInsertId());
    }

    public function update(object $entity): void
    {
        if (!$entity instanceof Producto || $entity->getIdProducto() === null) {
            throw new InvalidArgumentException('Entidad inválida o sin ID para update.');
        }

        $sql = 'UPDATE productos SET
            nombre = :nombre,
            descripcion = :descripcion,
            id_categoria = :id_categoria,
            stock = :stock,
            precio = :precio,
            marca = :marca,
            modelo = :modelo,
            caracteristicas = :caracteristicas,
            codigo_interno = :codigo_interno,
            imagen_principal = :imagen_principal,
            favorito = :favorito,
            activo = :activo
            WHERE id_producto = :id';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':id'               => $entity->getIdProducto(),
            ':nombre'           => $entity->getNombre(),
            ':descripcion'      => $entity->getDescripcion(),
            ':id_categoria'     => $entity->getIdCategoria(),
            ':stock'            => $entity->getStock(),
            ':precio'           => $entity->getPrecio(),
            ':marca'            => $entity->getMarca(),
            ':modelo'           => $entity->getModelo(),
            ':caracteristicas'  => $entity->getCaracteristicas(),
            ':codigo_interno'   => $entity->getCodigoInterno(),
            ':imagen_principal' => $entity->getImagenesAsJson(),
            ':favorito'         => $entity->isFavorito() ? 1 : 0,
            ':activo'           => $entity->isActivo() ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM productos WHERE id_producto = :id');
        $st->execute([':id' => $id]);
    }

    private function hydrate(array $row): Producto
    {
        $p = Producto::fromArray($row);
        // id_producto ya se setea dentro de fromArray si está presente
        return $p;
    }
}
