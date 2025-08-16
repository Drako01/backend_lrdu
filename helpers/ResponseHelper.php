<?php

/**
 * Clase ResponseHelper - Gestiona las respuestas HTTP
 */
class ResponseHelper
{

    private static bool $testMode = false;

    public static function enableTestMode(): void
    {
        self::$testMode = true;
    }

    public static function disableTestMode(): void
    {
        self::$testMode = false;
    }

    public static function isTestMode(): bool
    {
        return self::$testMode;
    }

    /**
     * Envía una respuesta HTTP con un código de estado y datos JSON.
     *
     * @param int $statusCode Código de estado HTTP.
     * @param array $data Datos a enviar en formato JSON.
     */
    public static function sendResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!self::$testMode) {
            exit;
        }
    }

    /**
     * Envía una respuesta de éxito.
     *
     * @param array $data Datos del cuerpo de la respuesta.
     * @param int $statusCode Código de estado HTTP (default: 200).
     */
    public static function success(mixed $data = [], int $statusCode = 200, string $key = 'data'): void
    {
        self::sendResponse(
            $statusCode,
            [
                'status' => 'Ok',
                $key => $data,
                'code' => $statusCode
            ]
        );
    }

    public static function loguinSuccess(array $data = [], int $statusCode = 200): void
    {
        self::sendResponse(
            $statusCode,
            array_merge(
                [
                    'status' => 'Ok',
                    'code' => $statusCode
                ],
                $data
            )
        );
    }


    /**
     * Devolver una respuesta de error.
     *
     * @param array|string $messages Mensaje(s) de error.
     * @param int $statusCode Código de estado HTTP.
     */
    public static function error($messages, int $statusCode = 400): void
    {
        self::sendResponse(
            $statusCode,
            [
                'status' => 'Error',
                'error' => $messages,
                'code' => $statusCode,
            ]
        );
    }

    /**
     * Envía una respuesta de error de servidor.
     *
     * @param array|string $message Mensaje de error o datos adicionales en caso de array.
     * @param int $statusCode Código de estado HTTP (default: 500).
     */
    public static function serverError($message, int $statusCode = 500): void
    {
        $data = is_array($message) ? $message : ['error' => $message];
        self::sendResponse(
            $statusCode,
            [
                'status' => 'Error',
                'message' => $data,
                'code' => $statusCode,
            ]
        );
    }

    /**
     * Devolver una respuesta de error.
     *
     * @param array|string $messages Mensaje(s) de error.
     * @param int $statusCode Código de estado HTTP.
     */
    public static  function respondWithError($message, $statusCode)
    {
        $messages = is_array($message) ? implode(', ', $message) : $message;
        self::error($messages, $statusCode);
    }

    /**
     * Envía una respuesta sin contenido para el código de estado 204.
     */
    public static function noContent(): void
    {
        http_response_code(204);
    }
}
