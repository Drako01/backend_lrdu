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

    public function create(array $data): Banner
    {
        $url = trim((string)($data['banner'] ?? ''));
        if ($url === '') {
            throw new InvalidArgumentException('Se requiere la URL del banner.');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL de banner inválida.');
        }

        $b = Banner::fromArray(['banner' => $url]);
        $this->repo->save($b);
        return $b;
    }

    /** @return Banner[] */
    public function getAll(): array
    {
        return $this->repo->findAll();
    }

    public function getById(int $id): ?Banner
    {
        if ($id <= 0) throw new InvalidArgumentException('ID inválido.');
        return $this->repo->findById($id);
    }

    public function update(int $id, array $data): void
    {
        $b = $this->getById($id);
        if (!$b) throw new InvalidArgumentException("Banner $id no encontrado.");

        if (array_key_exists('banner', $data)) {
            $url = $data['banner'];
            if ($url !== null) {
                $url = trim((string)$url);
                if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('URL de banner inválida.');
                }
                $b->setUrlBanner($url);
            }
        }

        $this->repo->update($b);
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) throw new InvalidArgumentException('ID inválido.');
        return $this->repo->delete($id);
    }
}
