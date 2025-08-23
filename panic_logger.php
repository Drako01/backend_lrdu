<?php
declare(strict_types=1);

// Directorio de logs (queda en webroot/multimedia/_logs)
$logDir = __DIR__ . '/multimedia/_logs';
@mkdir($logDir, 0775, true);

ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');
ini_set('display_errors', '0'); // no mostrar en pantalla
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) use ($logDir) {
    error_log("[PHP ERROR] $message in $file:$line");
});

set_exception_handler(function ($ex) {
    error_log("[UNCAUGHT] {$ex->getMessage()} in {$ex->getFile()}:{$ex->getLine()}\n{$ex->getTraceAsString()}");
});

register_shutdown_function(function () use ($logDir) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = "[FATAL] {$e['message']} in {$e['file']}:{$e['line']}";
        @file_put_contents($logDir . '/php-fatal.log', date('c') . ' ' . $msg . "\n", FILE_APPEND);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Fatal capturado. Revisá multimedia/_logs/php-fatal.log";
    }
});
