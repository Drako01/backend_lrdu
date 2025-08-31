<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../repositories/CategoriaRepository.php';
require_once __DIR__ . '/../models/Categoria.php';
#endregion

final class CategoriaService
{
    private CategoriaRepository $repo;

    public function __construct(?CategoriaRepository $repo = null)
    {
        $this->repo = $repo instanceof CategoriaRepository ? $repo : new CategoriaRepository();
    }

    public function create(array $data): Categoria
    {
        $nombre = trim((string)($data['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            throw new InvalidArgumentException('El nombre de la categoría es requerido y debe tener hasta 150 caracteres.');
        }

        // si ya existe -> 400
        if ($this->repo->existsByNombre($nombre)) {
            throw new InvalidArgumentException("La categoría '{$nombre}' ya está en uso.");
        }

        $cat = new Categoria($nombre);
        $this->repo->save($cat);
        return $cat;
    }


    public function getAll(): array
    {
        return $this->repo->findAll();
    }

    public function getById(int $id): ?Categoria
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de categoría inválido.');
        }
        return $this->repo->findById($id);
    }

    public function update(int $id, array $data): void
    {
        $cat = $this->getById($id);
        if (!$cat) {
            throw new InvalidArgumentException("Categoría $id no encontrada.");
        }

        if (isset($data['nombre'])) {
            $nombre = trim((string)$data['nombre']);
            if ($nombre === '' || mb_strlen($nombre) > 150) {
                throw new InvalidArgumentException('Nombre inválido.');
            }

            // solo si realmente cambia (case-insensitive)
            if (strcasecmp($nombre, $cat->getNombre()) !== 0) {
                if ($this->repo->existsByNombre($nombre, $cat->getIdCat())) {
                    throw new InvalidArgumentException("La categoría '{$nombre}' ya está en uso.");
                }
                $cat->setNombre($nombre);
            }
        }

        $this->repo->update($cat);
    }


    public function delete(int $id): bool
    {
        $cat = $this->repo->findById($id);
        if (!$cat) return false;
        $this->repo->delete($id);
        return true;
    }

    public function getAllWithCounts(): array
    {
        return $this->repo->findAllWithCounts();
    }

    public function getCountByCategory(int $id): int
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID de categoría inválido.');
        }
        return $this->repo->countProductsByCategory($id);
    }
}
