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
        if (!$conn instanceof PDO) throw new RuntimeException('No se pudo obtener la conexión PDO.');
        $this->pdo = $conn;
    }

    public function findByName(string $bannerName): ?Banner
    {
        $st = $this->pdo->prepare('SELECT banner_name, image_url, active FROM banners WHERE banner_name = :bn');
        $st->execute([':bn' => $bannerName]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? Banner::fromArray($row) : null;
    }

    /** @return Banner[] size=2 */
    public function findAll(): array
    {
        $st = $this->pdo->query('SELECT banner_name, image_url, active FROM banners ORDER BY banner_name ASC');
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) $out[] = Banner::fromArray($row);
        return $out;
    }

    /** Upsert por banner_name; si $imageUrl===null no lo pisa (permite actualización parcial) */
    public function upsert(string $bannerName, ?string $imageUrl, ?bool $active): Banner
    {
        // 1) Traer actual
        $current = $this->findByName($bannerName);

        $finalUrl   = $imageUrl  ?? ($current?->getImageUrl() ?? null);
        $finalActive= $active    ?? ($current?->isActive() ?? false);

        $sql = 'INSERT INTO banners (banner_name, image_url, active)
                VALUES (:bn, :url, :act)
                ON DUPLICATE KEY UPDATE
                    image_url = VALUES(image_url),
                    active    = VALUES(active),
                    updated_at= CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':bn'  => $bannerName,
            ':url' => $finalUrl,
            ':act' => (int)$finalActive
        ]);

        return new Banner($bannerName, $finalUrl, $finalActive);
    }
}
