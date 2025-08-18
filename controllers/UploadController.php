<?php

require_once __DIR__ . '/../services/UploadService.php';

class UploadController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
    }

    public function handleUpload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }

        if (!isset($_FILES['file'], $_POST['username'], $_POST['character_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan parámetros requeridos']);
            return;
        }

        $username = $_POST['username'];
        $characterName = $_POST['character_name'];
        $file = $_FILES['file'];

        $info = $this->uploadService->saveUploadedFile($file, $username, $characterName);

        if ($info) {
            echo json_encode([
                'success' => true,
                'path' => $info['path'],
                'type' => $info['type']
            ]);
            error_log(json_encode($info));
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo guardar el archivo']);
        }
    }
}
