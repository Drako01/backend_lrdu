<?php
/**
 * Función para manejar las solicitudes CORS
 */
function handleCORS()
{
    // $allowedOrigins = ['https://ejemplo1.com', 'https://ejemplo2.com']; // Dominios permitidos

    // Validar el origen de la solicitud
    // if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    //     header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    // } else {
    //     header('Access-Control-Allow-Origin: http://localhost'); // Dominio por defecto
    // }
    header("Access-Control-Allow-Origin: *"); // Para liberar a cuelquier Dominio
    // Métodos permitidos
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    // Encabezados permitidos
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Permitir credenciales
    header('Access-Control-Allow-Credentials: true');

    // Manejo de solicitudes preflight (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // Sin contenido
        exit;
    }
}
