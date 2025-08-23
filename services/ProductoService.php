<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../repositories/ProductoRepository.php';
require_once __DIR__ . '/../repositories/CategoriaRepository.php';
require_once __DIR__ . '/../models/Producto.php';
#endregion

final class ProductoService
{
    private ProductoRepository $repo;
    private CategoriaRepository $catRepo;

    public function __construct(?ProductoRepository $repo = null, ?CategoriaRepository $catRepo = null)
    {
        $this->repo    = $repo    instanceof ProductoRepository ? $repo    : new ProductoRepository();
        $this->catRepo = $catRepo instanceof CategoriaRepository ? $catRepo : new CategoriaRepository();
    }

    public function create(array $data): Producto
    {
        // Validaciones base
        $nombre = trim((string)($data['nombre'] ?? ''));
        $idCat  = (int)($data['id_categoria'] ?? $data['idCategoria'] ?? 0);
        if ($nombre === '') throw new InvalidArgumentException('El nombre es requerido.');
        if ($idCat <= 0)    throw new InvalidArgumentException('id_categoria es requerido y debe ser > 0.');
        if (!$this->catRepo->findById($idCat)) {
            throw new InvalidArgumentException("La categoría $idCat no existe.");
        }

        $producto = Producto::fromArray($data);
        $this->repo->save($producto);
        return $producto;
    }

    /** @return Producto[] */
    public function getAll(array $filters): array
    {
        return $this->repo->findAllPaginated($filters); // ['items'=>[], 'total'=>int]
    }

    public function getById(int $id): ?Producto
    {
        if ($id <= 0) throw new InvalidArgumentException('ID inválido.');
        return $this->repo->findById($id);
    }

    public function update(int $id, array $data): void
    {
        $p = $this->getById($id);
        if (!$p) throw new InvalidArgumentException("Producto $id no encontrado.");

        // Actualizaciones parciales coherentes con el modelo
        if (isset($data['nombre']))            $p->setNombre((string)$data['nombre']);
        if (isset($data['descripcion']))       $p->setDescripcion($data['descripcion'] !== null ? (string)$data['descripcion'] : null);
        if (isset($data['id_categoria']))      $this->validateAndSetCategoria($p, (int)$data['id_categoria']);
        if (isset($data['idCategoria']))       $this->validateAndSetCategoria($p, (int)$data['idCategoria']);
        if (isset($data['stock']))             $p->setStock((int)$data['stock']);
        if (isset($data['precio']))            $p->setPrecio((float)$data['precio']);
        if (isset($data['marca']))             $p->setMarca($data['marca'] !== null ? (string)$data['marca'] : null);
        if (isset($data['modelo']))            $p->setModelo($data['modelo'] !== null ? (string)$data['modelo'] : null);
        if (isset($data['caracteristicas']))   $p->setCaracteristicas($data['caracteristicas'] !== null ? (string)$data['caracteristicas'] : null);
        if (isset($data['codigo_interno']))    $p->setCodigoInterno($data['codigo_interno'] !== null ? (string)$data['codigo_interno'] : null);
        if (isset($data['codigoInterno']))     $p->setCodigoInterno($data['codigoInterno'] !== null ? (string)$data['codigoInterno'] : null);
        if (array_key_exists('imagen_principal', $data)) {
            $p->setImagenPrincipal($data['imagen_principal']); // array|string|null
        }
        if (array_key_exists('imagenPrincipal', $data)) {
            $p->setImagenPrincipal($data['imagenPrincipal']);  // array|string|null
        }
        if (array_key_exists('video_url', $data)) {
            $p->setVideoUrl($data['video_url'] !== null ? (string)$data['video_url'] : null);
        }
        if (array_key_exists('videoUrl', $data)) {
            $p->setVideoUrl($data['videoUrl'] !== null ? (string)$data['videoUrl'] : null);
        }
        if (array_key_exists('favorito', $data)) $p->setFavorito((bool)$data['favorito']);
        if (array_key_exists('activo', $data))   $p->setActivo((bool)$data['activo']);

        $this->repo->update($p);
    }

    public function delete(int $id): bool
    {
        $p = $this->repo->findById($id);
        if (!$p) return false;
        $this->repo->delete($id);
        return true;
    }

    private function validateAndSetCategoria(Producto $p, int $idCat): void
    {
        if ($idCat <= 0) throw new InvalidArgumentException('id_categoria inválido.');
        if (!$this->catRepo->findById($idCat)) {
            throw new InvalidArgumentException("La categoría $idCat no existe.");
        }
        $p->setIdCategoria($idCat);
    }

    public function getCategoriaTag(int $idCat): string
    {
        $cat = $this->catRepo->findById($idCat);

        if ($cat === null) {
            return (string)$idCat;
        }

        // 1) Si es entidad con getter, usarlo
        if (is_object($cat) && method_exists($cat, 'getNombre')) {
            $name = (string)$cat->getNombre();
            if ($name !== '') {
                return $name;
            }
        }

        // 2) Si es array (o ArrayAccess), usar 'nombre'
        if (is_array($cat) && !empty($cat['nombre'])) {
            return (string)$cat['nombre'];
        }
        if ($cat instanceof ArrayAccess && isset($cat['nombre'])) {
            $name = (string)$cat['nombre'];
            if ($name !== '') {
                return $name;
            }
        }

        // 3) Fallback: el ID como tag
        return (string)$idCat;
    }
}
