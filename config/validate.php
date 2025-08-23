<?php
declare(strict_types=1);

require_once __DIR__ . '/environment.config.php';

use Config\Environment;
Environment::loadEnv();

/** Lee de $_ENV o getenv, normaliza a string y aplica default si falta */
if (!function_exists('envs')) {
    function envs(string $key, string $fallback = ''): string {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null) return $fallback;
        $s = (string)$v;
        return $s === '' ? $fallback : $s;
    }
}

/** rtrim seguro para rutas (soporta / y \) */
if (!function_exists('rtrim_path')) {
    function rtrim_path(string $p): string {
        return rtrim($p, "/\\");
    }
}

$environment = strtolower(envs('ENVIRONMENT', 'production'));
$isLocal     = $environment === 'local';

$common = [
    'MEDIA_BASE_DIR' => rtrim_path(envs('MEDIA_BASE_DIR', __DIR__ . '/../multimedia')),
    'MEDIA_BASE_URL' => rtrim(envs('MEDIA_BASE_URL', 'https://backend.losreyesdelusado.com.ar'), '/'),
];

if ($isLocal) {
    return $common + [
        // --- DB ---
        'DB_SERVER'   => envs('DB_SERVER',   '127.0.0.1'),
        'DB_USER'     => envs('DB_USER',     'root'),
        'DB_PASSWORD' => envs('DB_PASSWORD', 'Alejandro'),
        'DB_NAME'     => envs('DB_NAME',     'lrdu'),
        'DB_PORT'     => envs('DB_PORT',     '3306'),

        // --- App ---
        'URL_SERVER'  => envs('URL_LOCAL',  'http://localhost'),
        'HOST_SERVER' => envs('HOST_LOCAL', 'http://localhost'),
        'PORT_SERVER' => envs('PORT_LOCAL', '80'),
        'HOST_IP'     => envs('IP_LOCAL',   '127.0.0.1'),

        // --- Security ---
        'SECRET_KEY'  => envs('SECRET_KEY', ''),

        // --- SMTP (opcionales) ---
        'HOST_SMTP'       => envs('HOST_SMTP',       ''),
        'USERNAME_SMTP'   => envs('USERNAME_SMTP',   ''),
        'PASS_SMTP'       => envs('PASS_SMTP',       ''),
        'PORT_SMTP'       => envs('PORT_SMTP',       ''),
        'SMTP_SECURE'     => envs('SMTP_SECURE',     'tls'),
        'EMAIL_FROM'      => envs('EMAIL_FROM',      envs('USERNAME_SMTP', '')),
        'EMAIL_FROM_NAME' => envs('EMAIL_FROM_NAME', 'Los Reyes del Usado'),
    ];
}

return $common + [
    // --- DB ---
    'DB_SERVER'   => envs('DB_SERVER_PROD',   '127.0.0.1'),
    'DB_USER'     => envs('DB_USER_PROD',     'admin'),
    'DB_PASSWORD' => envs('DB_PASSWORD_PROD', ''),
    'DB_NAME'     => envs('DB_NAME_PROD',     ''),
    'DB_PORT'     => envs('DB_PORT_PROD',     '3306'),

    // --- App ---
    'URL_SERVER'  => envs('URL_PRODUCTION',  'https://localhost'),
    'HOST_SERVER' => envs('HOST_PRODUCTION', 'https://localhost'),
    'PORT_SERVER' => envs('PORT_PRODUCTION', '443'),
    'HOST_IP'     => envs('IP_PRODUCTION',   '127.0.0.1'),

    // --- Security ---
    'SECRET_KEY'  => envs('SECRET_KEY', ''),

    // --- SMTP (opcionales) ---
    'HOST_SMTP'       => envs('HOST_SMTP',       ''),
    'USERNAME_SMTP'   => envs('USERNAME_SMTP',   ''),
    'PASS_SMTP'       => envs('PASS_SMTP',       ''),
    'PORT_SMTP'       => envs('PORT_SMTP',       ''),
    'SMTP_SECURE'     => envs('SMTP_SECURE',     'tls'),
    'EMAIL_FROM'      => envs('EMAIL_FROM',      envs('USERNAME_SMTP', '')),
    'EMAIL_FROM_NAME' => envs('EMAIL_FROM_NAME', 'Los Reyes del Usado'),
];
