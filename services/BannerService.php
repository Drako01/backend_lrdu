<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../repositories/BannerRepository.php';
require_once __DIR__ . '/../models/Banner.php';
#endregion

final class BannerService
{
    private BannerRepository $repo;

    public function __construct(?BannerRepository $repo = null)
    {
        $this->repo = $repo ?? new BannerRepository();
    }

    public static function validateBannerName(string $name): string
    {
        $name = trim(strtolower($name));
        if (!in_array($name, ['banner_top','banner_bottom'], true)) {
            throw new InvalidArgumentException('slot/banner_name inválido (esperado: banner_top | banner_bottom)');
        }
        return $name;
    }

    /** GET público */
    public function get(?string $slot): array
    {
        if ($slot) {
            $slot = self::validateBannerName($slot);
            $b = $this->repo->findByName($slot);
            if (!$b) $b = new Banner($slot, null, false); // si no existiera (por si falta seed)
            return ['banner' => $b->toArray()];
        }

        $items = $this->repo->findAll();
        // garantizamos ambos slots
        $map = array_column(array_map(fn($b)=>$b->toArray(), $items), null, 'banner_name');
        foreach (['banner_top','banner_bottom'] as $slotName) {
            if (!isset($map[$slotName])) $map[$slotName] = (new Banner($slotName, null, false))->toArray();
        }
        // orden estable
        $out = [$map['banner_top'], $map['banner_bottom']];
        return ['banners' => $out];
    }

    /** POST admin */
    public function update(string $bannerName, ?string $activeRaw, ?string $imageUrlFromUpload): Banner
    {
        $name = self::validateBannerName($bannerName);
        $active = self::parseActive($activeRaw); // null si no vino, bool si vino
        // upsert parcial
        return $this->repo->upsert($name, $imageUrlFromUpload, $active);
    }

    private static function parseActive(?string $raw): ?bool
    {
        if ($raw === null) return null;
        $val = strtolower(trim($raw));
        if ($val === '1' || $val === 'true')  return true;
        if ($val === '0' || $val === 'false') return false;
        // si llega vacío, lo tratamos como null (no pisa)
        if ($val === '') return null;
        throw new InvalidArgumentException('active inválido (usar true/false o 1/0)');
    }
}
