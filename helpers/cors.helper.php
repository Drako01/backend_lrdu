<?php
function handleCORS(): void
{
    // ✅ Lista blanca (ajustá según tus frontends)
    $allowedOrigins = [
        'https://losreyesdelusado.com.ar',
        'https://backend.losreyesdelusado.com.ar', // si llamás desde el mismo host
        'https://admin.losreyesdelusado.com.ar',
        'http://localhost:3000',
        'http://localhost:5173',
    ];

    $method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $origin     = $_SERVER['HTTP_ORIGIN']    ?? '';
    $reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, Authorization, X-Requested-With';
    $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    header('Vary: Origin');

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        // 🔒 Origen permitido: habilito credenciales (cookies/autorización)
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    } else {
        // 🌍 Fallback abierto (dev): sin credenciales
        header('Access-Control-Allow-Origin: *');
        // OJO: NO mandar Allow-Credentials con '*'
    }

    // Estos van tanto en preflight como en request normal
    header("Access-Control-Allow-Methods: $allowMethods");
    header("Access-Control-Allow-Headers: $reqHeaders");
    header('Access-Control-Expose-Headers: Content-Type, Authorization, Content-Length');
    header('Access-Control-Max-Age: 86400');

    // Preflight
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
