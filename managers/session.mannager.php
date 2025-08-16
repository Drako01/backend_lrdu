<?php

class SessionManager
{

    public static function startSession()
    {
        // 📌 Configurar sesiones para usar archivos (NO Redis)
        // ini_set('session.save_handler', 'files');
        // ini_set('session.save_path', 'C:\php\tmp'); // 💾 Usa la misma ruta en login/logout

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function destroySession()
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // 📌 Configurar la misma carpeta antes de destruir la sesión
        // ini_set('session.save_handler', 'files');
        // ini_set('session.save_path', 'C:\php\tmp'); // 💾 Asegurar que usa la misma carpeta

        // 📌 Destruir la sesión
        session_destroy();
    }
}
