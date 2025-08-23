<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../services/BannerService.php';
require_once __DIR__ . '/../services/BannerMediaService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
#endregion

final class BannerController
{
    private BannerService $service;
    private BannerMediaService $media;

    public function __construct(?BannerService $service = null, ?BannerMediaService $media = null)
    {
        $this->service = $service ?? new BannerService();
        $this->media   = $media   ?? new BannerMediaService();
    }

    /** GET /banners */
    public function list(): void
    {
        try {
            $items = $this->service->getAll();
            $out = array_map(static fn($b) => $b->toArray(), $items);
            ResponseHelper::success($out, 200, 'banners');
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }

    /** GET /banners/{id} */
    public function getById(int $id): void
    {
        try {
            $b = $this->service->getById($id);
            if (!$b) {
                ResponseHelper::error("Banner $id no encontrado.", 404);
                return;
            }
            ResponseHelper::success($b->toArray(), 200, 'banner');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }

    /** POST /banners  (multipart o JSON) */
    public function create(mixed $params = null): void
    {
        try {
            $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $isMultipart = (stripos($ct, 'multipart/form-data') !== false) || !empty($_FILES);

            if ($isMultipart) {
                // Subida de imagen única:
                // acepta aliases: banner | image | imagen | file
                $url = $this->media->saveForBanner($_FILES);
                $data = ['banner' => $url];
            } else {
                $data = $params ?? json_decode(file_get_contents('php://input'), true) ?? [];
            }

            $b = $this->service->create($data);
            ResponseHelper::success($b->toArray(), 201, 'banner');
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

    /** PUT/PATCH /banners/{id} (multipart o JSON) */
    public function update(int $id, mixed $data = null): void
    {
        try {
            $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $isMultipart = (stripos($ct, 'multipart/form-data') !== false) || !empty($_FILES);

            if ($isMultipart) {
                // Si viene un archivo nuevo, lo reemplazamos
                $maybeNew = $this->media->saveForBanner($_FILES, allowEmpty: true);
                $arr = [];
                if ($maybeNew !== null) {
                    $arr['banner'] = $maybeNew;
                }
            } else {
                $arr = is_array($data) ? $data : (json_decode((string)$data, true) ?? []);
            }

            $this->service->update($id, $arr ?? []);
            ResponseHelper::success("El banner $id fue actualizado correctamente.", 200);
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

    /** DELETE /banners/{id} */
    public function delete(int $id): void
    {
        try {
            $ok = $this->service->delete($id);
            if (!$ok) {
                ResponseHelper::error("El banner $id no existe.", 404);
                return;
            }
            ResponseHelper::success("El banner $id fue eliminado correctamente.", 200);
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }
}
