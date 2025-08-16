<?php
declare(strict_types=1);

require_once __DIR__ . '/environment.config.php';

use Config\Environment;

Environment::loadEnv();

$environment = $_ENV['ENVIRONMENT'] ?? 'production';
$isLocal     = strtolower($environment) === 'local';

if ($isLocal) {
    return [
        // --- DB ---
        'DB_SERVER'   => $_ENV['DB_SERVER']      ?? '127.0.0.1',
        'DB_USER'     => $_ENV['DB_USER']        ?? 'root',
        'DB_PASSWORD' => $_ENV['DB_PASSWORD']    ?? '',
        'DB_NAME'     => $_ENV['DB_NAME']        ?? '',
        'DB_PORT'     => $_ENV['DB_PORT']        ?? '3306',

        // --- App ---
        'URL_SERVER'  => $_ENV['URL_LOCAL']      ?? 'http://localhost',
        'HOST_SERVER' => $_ENV['HOST_LOCAL']     ?? 'http://localhost',
        'PORT_SERVER' => $_ENV['PORT_LOCAL']     ?? '80',
        'HOST_IP'     => $_ENV['IP_LOCAL']       ?? '127.0.0.1',

        // --- Security ---
        'SECRET_KEY'  => $_ENV['SECRET_KEY']     ?? '',

        // --- SMTP (opcionales; si faltan, el mailer hace no-op sin romper) ---
        'HOST_SMTP'       => $_ENV['HOST_SMTP']       ?? null,
        'USERNAME_SMTP'   => $_ENV['USERNAME_SMTP']   ?? null,
        'PASS_SMTP'       => $_ENV['PASS_SMTP']       ?? null,
        'PORT_SMTP'       => $_ENV['PORT_SMTP']       ?? null,
        'SMTP_SECURE'     => $_ENV['SMTP_SECURE']     ?? 'tls', // tls|ssl
        'EMAIL_FROM'      => $_ENV['EMAIL_FROM']      ?? ($_ENV['USERNAME_SMTP'] ?? null),
        'EMAIL_FROM_NAME' => $_ENV['EMAIL_FROM_NAME'] ?? 'Los Reyes del Usado',
    ];
}

return [
    // --- DB ---
    'DB_SERVER'   => $_ENV['DB_SERVER_PROD']   ?? '127.0.0.1',
    'DB_USER'     => $_ENV['DB_USER_PROD']     ?? 'admin',
    'DB_PASSWORD' => $_ENV['DB_PASSWORD_PROD'] ?? '',
    'DB_NAME'     => $_ENV['DB_NAME_PROD']     ?? '',
    'DB_PORT'     => $_ENV['DB_PORT_PROD']     ?? '3306',

    // --- App ---
    'URL_SERVER'  => $_ENV['URL_PRODUCTION']   ?? 'https://localhost',
    'HOST_SERVER' => $_ENV['HOST_PRODUCTION']  ?? 'https://localhost',
    'PORT_SERVER' => $_ENV['PORT_PRODUCTION']  ?? '443',
    'HOST_IP'     => $_ENV['IP_PRODUCTION']    ?? '127.0.0.1',

    // --- Security ---
    'SECRET_KEY'  => $_ENV['SECRET_KEY']       ?? '',

    // --- SMTP (opcionales) ---
    'HOST_SMTP'       => $_ENV['HOST_SMTP']       ?? null,
    'USERNAME_SMTP'   => $_ENV['USERNAME_SMTP']   ?? null,
    'PASS_SMTP'       => $_ENV['PASS_SMTP']       ?? null,
    'PORT_SMTP'       => $_ENV['PORT_SMTP']       ?? null,
    'SMTP_SECURE'     => $_ENV['SMTP_SECURE']     ?? 'tls', // tls|ssl
    'EMAIL_FROM'      => $_ENV['EMAIL_FROM']      ?? ($_ENV['USERNAME_SMTP'] ?? null),
    'EMAIL_FROM_NAME' => $_ENV['EMAIL_FROM_NAME'] ?? 'Los Reyes del Usado',
];
