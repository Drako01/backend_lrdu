<?php
#region Imports
require_once __DIR__ . '/../config/environment.config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Config\Environment;

Environment::loadEnv();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
#endregion

/**
 * Clase para la seguridad JWT
 */
class SecurityJWT
{
    #region Manejo del Token genérico
    /**
     * Generar la clave secreta
     * 
     * @return string
     */
    private static function generateSecretKey()
    {
        return $_ENV['SECRET_KEY'];
    }

    /**
     * Valida un nombre de usuario.
     * Solo permite letras y números, con una longitud entre 4 y 12 caracteres.
     *
     * @param string $username Nombre de usuario a validar.
     * @return bool True si es válido, False si no.
     */
    public static function validateUsername($username)
    {
        return preg_match('/^[a-zA-Z0-9]{4,12}$/', $username);
    }

    /**
     * Genera un token JWT.
     * 
     * @param string $username Nombre de usuario.
     * @param string $ip Dirección IP del cliente.
     * @param int $exp Expiración del token (por defecto 1 hora).
     * @return string Token generado.
     */
    public static function generateToken($id, $username, $email, $role, $ip, $exp = 86400)
    {
        $now = time();

        $data = [
            'id'        => $id,
            'full_name' => $username,
            'email'     => $email,
            'role'      => $role,
            'ip'        => $ip,
            'iat'       => $now,
            'exp'       => $now + $exp
        ];


        // Se usa la clave secreta generada dinámicamente
        $secretKey = self::generateSecretKey();
        return JWT::encode($data, $secretKey, 'HS256');
    }

    /**
     * Decodifica un token JWT.
     *
     * @param string $token Token JWT a decodificar.
     * @return object Datos decodificados.
     * @throws Exception Si el token es inválido o ha expirado.
     */
    public static function decodeToken($token)
    {
        try {
            $secretKey = self::generateSecretKey();
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            // Se agrega más contexto al mensaje de error
            throw new Exception("Error al decodificar el token: " . $e->getMessage() . " - Token: $token");
        }
    }

    /**
     * Obtiene la dirección IP del cliente.
     *
     * @return string Dirección IP del cliente.
     */
    public static function getUserIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    // Dentro de class SecurityJWT

    /** Ruta del archivo de blacklist */
    private static function blacklistPath(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jwt_blacklist.json';
    }

    /** Carga la blacklist desde disco (purga expirados) */
    private static function loadBlacklist(): array
    {
        $file = self::blacklistPath();
        if (!is_file($file)) return [];

        $list = json_decode((string)file_get_contents($file), true) ?: [];
        $now  = time();

        // Purga expirados
        $changed = false;
        foreach ($list as $k => $exp) {
            if ((int)$exp <= $now) {
                unset($list[$k]);
                $changed = true;
            }
        }
        if ($changed) {
            file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return $list;
    }

    /** Guarda la blacklist en disco */
    private static function saveBlacklist(array $list): void
    {
        file_put_contents(self::blacklistPath(), json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Revoca un token hasta su expiración */
    public static function invalidateToken(string $token): bool
    {
        try {
            $decoded = self::decodeToken($token);
            $exp = isset($decoded->exp) ? (int)$decoded->exp : (time() + 3600);

            $list = self::loadBlacklist();
            $key  = hash('sha256', $token);
            $list[$key] = $exp;
            self::saveBlacklist($list);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Verifica si un token está revocado */
    public static function isTokenRevoked(string $token): bool
    {
        $list = self::loadBlacklist();
        $key  = hash('sha256', $token);
        return isset($list[$key]) && (int)$list[$key] > time();
    }

    /** Valida un token (agrega chequeo de blacklist) */
    // security/jwt.security.php

    public function validateToken(?string $token): ?object
    {
        if ($token === null) {
            return null;
        }

        // Revocado por logout (blacklist)
        if (self::isTokenRevoked($token)) {
            return null;
        }

        try {
            return self::decodeToken($token); // stdClass con ->id, ->exp, etc.
        } catch (Throwable $e) {
            return null;
        }
    }


    #endregion
}
