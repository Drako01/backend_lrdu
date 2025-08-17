<?php
declare(strict_types=1);

/**
 * 1) Hacer visible el Authorization para PHP-FPM/Apache (cPanel suele ponerlo en REDIRECT_HTTP_AUTHORIZATION)
 */
if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

/**
 * 2) Marcar HTTPS cuando estás detrás de Cloudflare / proxy (para que tu app no crea que es http)
 */
if (
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false)
) {
    $_SERVER['HTTPS'] = 'on';
}

/**
 * 3) IP real (útil para logs/claims). No piso REMOTE_ADDR: lo dejo en otra clave.
 */
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REAL_CLIENT_IP'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

require_once __DIR__ . '/server/Server.php';

$server = new Server();
$server->handleRequest();
