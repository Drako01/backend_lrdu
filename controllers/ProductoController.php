<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../services/ProductoService.php';
require_once __DIR__ . '/../services/ProductMediaService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

final class ProductoController
{
    private ProductoService $service;
    private ProductMediaService $media;
    private array $messages;

    public function __construct(?ProductoService $service = null, ?ProductMediaService $media = null)
    {
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        $this->service  = $service instanceof ProductoService ? $service : new ProductoService();
        $this->media    = $media   instanceof ProductMediaService ? $media   : new ProductMediaService();
    }

    /** POST /productos */
    public function create(?array $params = null): void
    {
        try {
            $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $isMultipart = (!empty($_FILES))
                || (is_string($ct) && stripos($ct, 'multipart/form-data') !== false);


            if ($isMultipart) {
                $data = $_POST; // campos del producto
                $files  = $_FILES;  
                $nombre = (string)($data['nombre'] ?? '');
                $idCat  = (int)($data['id_categoria'] ?? $data['idCategoria'] ?? 0);
                $catTag = $idCat > 0 ? $this->service->getCategoriaTag($idCat) : 'general';

                // Guarda media y mete URLs en $data
                $media = $this->media->saveForProducto($files, $nombre, $catTag);
                if (!empty($media['images'])) $data['imagen_principal'] = $media['images'];
                if (!empty($media['video']))  $data['video_url']        = $media['video'];
            } else {
                $data = $params ?? json_decode(file_get_contents('php://input'), true) ?? [];
            }

            $prod = $this->service->create($data);
            ResponseHelper::success($prod->toArray(), 201, 'producto');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                ResponseHelper::error('Violación de restricción (FK/Unique). ' . $e->getMessage(), 409);
            } else {
                ResponseHelper::serverError('DB Error: ' . $e->getMessage(), 500);
            }
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Error: ') . $e->getMessage(), 500);
        }
    }

    /** GET /productos */
    public function getAll(?array $query = []): void
    {
        try {
            // Toggle de paginación
            $perPageRaw = $query['per_page'] ?? 20;
            $paginationEnabled = true;

            if ($perPageRaw === 'all' || (int)$perPageRaw === 0) {
                $paginationEnabled = false;
                $perPage = null;
                $page    = 1;
                $offset  = null;
            } else {
                $perPage = max(1, min((int)$perPageRaw, 100));
                $page    = max(1, (int)($query['page'] ?? 1));
                $offset  = ($page - 1) * $perPage;
            }

            $allowedSort = ['fecha_creacion', 'precio', 'id_producto', 'nombre'];
            $sortBy  = $query['sort_by'] ?? 'fecha_creacion';
            if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'fecha_creacion';

            $sortDir = strtoupper($query['sort_dir'] ?? 'DESC');
            $sortDir = $sortDir === 'ASC' ? 'ASC' : 'DESC';

            $filters = [
                'category'  => isset($query['category']) ? (int)$query['category'] : null,
                'search'    => isset($query['search']) ? trim((string)$query['search']) : null,
                'min_price' => isset($query['min_price']) ? (float)$query['min_price'] : null,
                'max_price' => isset($query['max_price']) ? (float)$query['max_price'] : null,
                'in_stock'  => array_key_exists('in_stock', $query)
                    ? filter_var($query['in_stock'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                    : null,
                'brand'     => isset($query['brand']) ? trim((string)$query['brand']) : null,
                'model'     => isset($query['model']) ? trim((string)$query['model']) : null,

                'sort_by'   => $sortBy,
                'sort_dir'  => $sortDir,

                // paginación (pueden ser null si viene "all")
                'limit'     => $perPage,
                'offset'    => $offset,
            ];

            // ['items'=>Producto[], 'total'=>int]
            $result = $this->service->getAll($filters);

            $items = array_map(fn(Producto $p) => $p->toArray(), $result['items'] ?? []);
            $total = (int)($result['total'] ?? count($items));

            $payload = [
                'items' => $items,
                'pagination' => [
                    'page'        => $paginationEnabled ? $page : 1,
                    'per_page'    => $paginationEnabled ? $perPage : 'all',
                    'total'       => $total,
                    'total_pages' => $paginationEnabled ? (int)max(1, ceil($total / $perPage)) : 1,
                ],
                'filters_applied' => array_filter([
                    'category'  => $filters['category'],
                    'search'    => $filters['search'],
                    'min_price' => $filters['min_price'],
                    'max_price' => $filters['max_price'],
                    'in_stock'  => $filters['in_stock'],
                    'brand'     => $filters['brand'],
                    'model'     => $filters['model'],
                    'sort_by'   => $filters['sort_by'],
                    'sort_dir'  => $filters['sort_dir'],
                ], fn($v) => $v !== null && $v !== ''),
            ];

            ResponseHelper::success($payload, 200, 'productos');
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Error: ') . $e->getMessage(), 500);
        }
    }

    /** GET /productos/{id} */
    public function getById(int $id): void
    {
        try {
            $p = $this->service->getById($id);
            if (!$p) {
                ResponseHelper::error("El producto $id no existe.", 404);
                return;
            }
            ResponseHelper::success($p->toArray(), 200, 'producto');
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Error: ') . $e->getMessage(), 500);
        }
    }

    /** PUT /productos/{id} */
    public function update(int $id, mixed $data): void
    {
        try {
            $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $isMultipart = (stripos($ct, 'multipart/form-data') !== false) || !empty($_FILES);

            if ($isMultipart) {
                $arr = $_POST;
                // si mandan nuevas imágenes/video, reemplazamos lo existente
                $nombre = (string)($arr['nombre'] ?? '');
                // si no mandan nombre en el form de update, intento leerlo del actual:
                if ($nombre === '') {
                    $actual = $this->service->getById($id);
                    if ($actual) $nombre = $actual->getNombre();
                }

                $idCat = (int)($arr['id_categoria'] ?? $arr['idCategoria'] ?? 0);
                if ($idCat <= 0) {
                    $actual = $this->service->getById($id);
                    if ($actual) $idCat = $actual->getIdCategoria();
                }
                $catTag = $idCat > 0 ? $this->service->getCategoriaTag($idCat) : 'general';

                $media = $this->media->saveForProducto($_FILES, $nombre, $catTag);
                if (!empty($media['images'])) $arr['imagen_principal'] = $media['images'];
                if (!empty($media['video']))  $arr['video_url']        = $media['video'];
            } else {
                $arr = is_array($data) ? $data : (json_decode((string)$data, true) ?? []);
            }

            $this->service->update($id, $arr);
            ResponseHelper::success("El producto $id fue actualizado correctamente.", 200);
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                ResponseHelper::error('Violación de restricción (FK/Unique). ' . $e->getMessage(), 409);
            } else {
                ResponseHelper::serverError('DB Error: ' . $e->getMessage(), 500);
            }
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE /productos/{id} */
    public function delete(int $id): void
    {
        try {
            $ok = $this->service->delete($id);
            if (!$ok) {
                ResponseHelper::error("El producto $id no existe.", 404);
                return;
            }
            ResponseHelper::success("El producto $id fue eliminado correctamente.", 200);
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }
}
