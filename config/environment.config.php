<?php

namespace Config;

/**
 * Clase Environment - Carga las variables de entorno
 */
class Environment
{
    /**
     * Cargar las variables de entorno desde un archivo .env
     * 
     * @param string $file
     * 
     * @Drako01
     */
    public static function loadEnv($file = __DIR__ . '/../.env')
    {
        if (!file_exists($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}
