<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../services/ProductoService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

final class ProductoController
{
    private ProductoService $service;
    private array $messages;

    public function __construct(?ProductoService $service = null)
    {
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        $this->service  = $service instanceof ProductoService ? $service : new ProductoService();
    }

    /** POST /productos */
    public function create(?array $params = null): void
    {
        try {
            $data = $params ?? json_decode(file_get_contents('php://input'), true) ?? [];
            $prod = $this->service->create($data);
            ResponseHelper::success($prod->toArray(), 201, 'producto');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (PDOException $e) {
            // Unique / FK
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
    public function getAll(): void
    {
        try {
            $list = $this->service->getAll();
            $payload = array_map(fn(Producto $p) => $p->toArray(), $list);
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
            $arr = is_array($data) ? $data : (json_decode((string)$data, true) ?? []);
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
