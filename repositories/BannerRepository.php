<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/../models/Banner.php';
#endregion

final class BannerRepository
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

    public function save(Banner $entity): void
    {
        $sql = 'INSERT INTO banners (banner) VALUES (:banner)';
        $st  = $this->pdo->prepare($sql);
        $st->execute([':banner' => $entity->getUrlBanner()]);
        $entity->setIdBanner((int)$this->pdo->lastInsertId());
    }

    public function findById(int $id): ?Banner
    {
        $st = $this->pdo->prepare('SELECT * FROM banners WHERE id_banner = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? Banner::fromArray($row) : null;
    }

    /** @return Banner[] */
    public function findAll(): array
    {
        $st = $this->pdo->query('SELECT * FROM banners ORDER BY fecha_creacion DESC, id_banner DESC');
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = Banner::fromArray($row);
        }
        return $out;
    }

    public function update(Banner $entity): void
    {
        $sql = 'UPDATE banners SET banner = :banner WHERE id_banner = :id';
        $st  = $this->pdo->prepare($sql);
        $st->execute([
            ':banner' => $entity->getUrlBanner(),
            ':id'     => $entity->getIdBanner(),
        ]);
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM banners WHERE id_banner = :id');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
