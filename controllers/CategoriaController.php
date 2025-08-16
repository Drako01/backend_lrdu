<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../services/CategoriaService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

final class CategoriaController
{
    private CategoriaService $service;
    private array $messages;

    public function __construct(?CategoriaService $service = null)
    {
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        $this->service  = $service instanceof CategoriaService ? $service : new CategoriaService();
    }

    /** POST /categorias */
    public function create(?array $params = null): void
    {
        try {
            $data = $params ?? json_decode(file_get_contents('php://input'), true) ?? [];
            $cat  = $this->service->create($data);
            ResponseHelper::success($cat->toArray(), 201, 'categoria');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Error: ') . $e->getMessage(), 500);
        }
    }

    /** GET /categorias */
    public function getAll(): void
    {
        try {
            $cats = $this->service->getAll();
            $payload = array_map(fn(Categoria $c) => $c->toArray(), $cats);
            ResponseHelper::success($payload, 200, 'categorias');
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Error: ') . $e->getMessage(), 500);
        }
    }

    /** GET /categorias/{id} */
    public function getById(int $id): void
    {
        try {
            $cat = $this->service->getById($id);
            if (!$cat) {
                ResponseHelper::error("La categoría $id no existe.", 404);
                return;
            }
            ResponseHelper::success($cat->toArray(), 200, 'categoria');
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['SERVER_ERROR'] ?? 'Error: ') . $e->getMessage(), 500);
        }
    }

    /** PUT /categorias/{id} */
    public function update(int $id, mixed $data): void
    {
        try {
            $arr = is_array($data) ? $data : (json_decode((string)$data, true) ?? []);
            $this->service->update($id, $arr);
            ResponseHelper::success("La categoría $id fue actualizada correctamente.", 200);
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError("Error del servidor: " . $e->getMessage(), 500);
        }
    }

    /** DELETE /categorias/{id} */
    public function delete(int $id): void
    {
        try {
            $ok = $this->service->delete($id);
            if (!$ok) {
                ResponseHelper::error("La categoría $id no existe.", 404);
                return;
            }
            ResponseHelper::success("La categoría $id fue eliminada correctamente.", 200);
        } catch (PDOException $e) {
            // FK en productos: MySQL 1451 (SQLSTATE 23000)
            if ($e->getCode() === '23000') {
                ResponseHelper::error('No se puede eliminar la categoría: tiene productos asociados.', 409);
            } else {
                ResponseHelper::serverError('Error de base de datos: ' . $e->getMessage(), 500);
            }
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }
}
