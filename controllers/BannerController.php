<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../services/BannerService.php';
require_once __DIR__ . '/../services/BannerMediaService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
// require_once __DIR__ . '/../middlewares/AuthMiddleware.php'; // si tenés uno
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

    /** GET /auth/get-banner?slot=banner_top */
    public function apiGetBanner(): void
    {
        try {
            $slot = isset($_GET['slot']) ? (string)$_GET['slot'] : null;
            $result = $this->service->get($slot);

            // Cache sugerido
            header('Cache-Control: max-age=60, stale-while-revalidate=300');

            if (isset($result['banner'])) {
                ResponseHelper::success($result['banner'], 200, 'banner');
            } else {
                ResponseHelper::success($result['banners'], 200, 'banners');
            }
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }

    /** POST /auth/update-banner  (multipart/form-data) */
    public function apiUpdateBanner(): void
    {
        try {
            // (Opcional) exigir token si tenés middleware
            // (new AuthMiddleware())->verifyAuthenticated(); // adaptá al tuyo

            $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            if (stripos($ct, 'multipart/form-data') === false) {
                ResponseHelper::error('Content-Type inválido: enviar multipart/form-data', 400);
                return;
            }

            $bannerName = $_POST['banner_name'] ?? null;
            if (!$bannerName) throw new InvalidArgumentException('banner_name requerido (banner_top | banner_bottom)');

            // Si vino imagen, subimos
            $imageUrl = null;
            if (!empty($_FILES['image'])) {
                $imageUrl = $this->media->saveImageForSlot($_FILES, (string)$bannerName, allowEmpty: false);
            }

            $activeRaw = $_POST['active'] ?? null;

            $b = $this->service->update((string)$bannerName, $activeRaw, $imageUrl);

            // 200/201: si no existía antes, técnicamente 201; para simplificar dejamos 200
            ResponseHelper::success($b->toArray(), 200, 'banner');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError('Error del servidor: ' . $e->getMessage(), 500);
        }
    }
}
