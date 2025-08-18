<?php
require_once __DIR__ . '/../security/jwt.security.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../services/UserService.php';


/**
 * Middleware para autenticación y autorización
 */
class AuthMiddleware
{

    protected bool $testMode = false;

    public function __construct()
    {
        $this->testMode = ResponseHelper::isTestMode();
    }

    #region Instancias Encapsuladas
    private  function getJwtInstance(): SecurityJWT
    {
        return new SecurityJWT();
    }
    #endregion

    #region Metodos de Autenticacion y Verificacion de Usuarios
    /**
     * Middleware que verifica si el usuario está autenticado.
     *
     * Este método realiza las siguientes acciones:
     * 1. Inicia una sesión si aún no está activa.
     * 2. Obtiene los encabezados de la solicitud y verifica la existencia del encabezado `Authorization`.
     * 3. Extrae y valida el token JWT presente en el encabezado `Authorization`.
     * 4. Compara los datos del token decodificado con los datos de la sesión activa del usuario.
     * 5. Si la autenticación es válida, permite continuar con la solicitud llamando al middleware o función siguiente.
     * 6. Si ocurre un error en cualquier paso, envía una respuesta JSON con el estado del error y detiene la ejecución.
     *
     * @param mixed $request Objeto de la solicitud HTTP.
     * @param mixed $response Objeto de la respuesta HTTP.
     * @param callable $next Función o middleware que se ejecutará si la autenticación es válida.
     * 
     * @return mixed Devuelve la respuesta procesada por el siguiente middleware o función.
     * 
     * @throws \Exception Si ocurre un error interno durante el proceso de autenticación.
     * 
     * Respuestas posibles:
     * - 401: Cuando el encabezado `Authorization` no está presente o los datos de la sesión no 
     *        coinciden con el token.
     * - 403: Cuando el token es inválido o no se puede validar correctamente.
     * - 500: Cuando ocurre un error interno en el servidor.
     * 
     * * Testeado
     */
    public function isAuthenticated($request, $response, $next)
    {
        try {
            $this->getUserFromToken();

            // Si todo es válido, continúa la solicitud
            return $next($request, $response);
        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => 'Error Interno del Servidor.',
            ], 500);
        }
    }

    /**
     *  * Testeado
     */
    public function validateTokenAndSession()
    {
        try {
            $user = $this->getUserFromToken(); // ya valida y responde si falla
            if (!$user || !isset($user['id'])) {
                throw new \RuntimeException('Token inválido o no contiene un ID válido.');
            }
            return $user['id'];
        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'status'  => 'Error',
                'message' => 'Error Interno del Servidor.',
            ], 500);
        }
    }

    /**
     * Obtiene el rol del usuario autenticado. - Testeado
     *
     * @return string|null Rol del usuario o null si no está autenticado
     */
    public function getAuthenticatedUserRole(): ?string
    {
        $token = $this->getHeadersAndExtractToken();
        $decodedToken = (new SecurityJWT())->validateToken($token);

        if (!is_object($decodedToken)) {
            return null;
        }

        $userId = $decodedToken->id ?? null;
        if (!$userId) return null;

        $userService = new UserService();
        $user = $userService->getUserById($userId);

        return $user ? $user->getRole()->value : null;
    }

    #endregion

    #region Metodos de Validacion de Roles de Usuarios
    /**
     * Verifica si el usuario tiene uno de los roles permitidos.
     *
     * @param array $allowedRoles Roles permitidos para acceder a la ruta
     * @param mixed $request Objeto de la solicitud
     * @param mixed $response Objeto de la respuesta
     * @param callable $next Siguiente función o middleware
     * @return mixed
     *  - Testeado
     */
    public function authorize(array $allowedRoles, $param1, $param2, callable $next)
    {
        $userRole = $this->getAuthenticatedUserRole();
        if (!in_array($userRole, $allowedRoles, true)) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => "Acceso denegado. Tu rol no tiene permiso para esta acción.",
            ], 403);
        }

        return $next();
    }

    /**
     * Middleware para excluir roles específicos (como USER_ROLE).
     *
     * @param string $excludedRole Rol que debe ser excluido
     * @param mixed $request Objeto de la solicitud
     * @param mixed $response Objeto de la respuesta
     * @param callable $next Siguiente función o middleware
     * @return mixed
     * - Testeado
     */
    public function excludeRole(string $excludedRole, $request, $response, $next)
    {
        $userRole = $this->getAuthenticatedUserRole();

        if ($userRole === $excludedRole) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => "Acceso denegado. Los usuarios con rol $excludedRole no tienen permiso para esta acción.",
            ], 403);
        }

        return $next($request, $response);
    }

    /**
     * Middleware para verificar un rol específico (ej: ADMIN).
     *
     * @param string $requiredRole Rol requerido para acceder a la ruta
     * @param mixed $request Objeto de la solicitud
     * @param mixed $response Objeto de la respuesta
     * @param callable $next Siguiente función o middleware
     * @return mixed
     * - Testeado
     */
    public function requireSpecificRole(string $requiredRole, $request, $response, $next)
    {

        $userRole = $this->getAuthenticatedUserRole();

        if ($userRole !== $requiredRole) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => 'Acceso denegado. Se requiere el rol de Administrador',
            ], 403);
        }

        return $next($request, $response);
    }

    /**
     * Autoriza el acceso basado en los roles permitidos, excluyendo los roles especificados
     *
     * @param array $allowedRoles Lista de roles permitidos
     * @param array $excludeRoles Lista de roles a excluir
     * @param callable $next Función a ejecutar si el acceso es autorizado
     * @return mixed
     * - Testeado
     */
    public function authorizeExcludingRoles(array $allowedRoles, array $excludeRoles, callable $next)
    {
        $filteredRoles = array_diff($allowedRoles, $excludeRoles);

        $userRole = $this->getAuthenticatedUserRole();

        if ($userRole === null || !in_array($userRole, $filteredRoles)) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => 'Acceso denegado. No tienes permiso para realizar esta acción.',
            ], 403);
        }

        return $next();
    }


    /**
     * Middleware para verificar que el usuario tenga uno de los roles específicos.
     *
     * @param array $requiredRoles Lista de roles requeridos para acceder a la ruta
     * @param mixed $request Objeto de la solicitud
     * @param mixed $response Objeto de la respuesta
     * @param callable $next Siguiente función o middleware
     * @return mixed
     * - Testeado
     */
    public  function requireManySpecificRoles(array $requiredRoles, $request, $response, $next)
    {

        $userRole = $this->getAuthenticatedUserRole();

        if ($userRole === null || !in_array($userRole, $requiredRoles, true)) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => 'Acceso denegado. No tienes permiso para realizar esta acción.',
            ], 403);
        }

        return $next($request, $response);
    }

    /**
     * Testeado
     */
    public  function allowAllRoles($request, $response, $next)
    {
        $allRoles = array_map(fn($role) => $role->value, Role::cases());
        return $this->requireManySpecificRoles($allRoles, $request, $response, $next);
    }


    #endregion

    #region Metodos Privados
    /**
     * Enviar una respuesta en formato JSON
     * 
     * @param array $data Datos a enviar
     * @param int $statusCode Código HTTP de la respuesta
     */
    protected function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        if (!$this->testMode) {
            $this->setHeaders($statusCode);
        }
        $responseBody = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($this->testMode) {
            throw new \RuntimeException("Mocked JSON Response: $responseBody");
        } else {
            $this->output($responseBody);
            exit;
        }
    }



    private function setHeaders(int $statusCode): void
    {
        header('Content-Type: application/json', true, $statusCode);
    }

    private function output(string $body): void
    {
        print $body;
    }



    // 🔹 Extrae Bearer token de todas las fuentes razonables
    private function extractBearerToken(): ?string
    {
        // 1) Unificar headers (getallheaders + SERVER + apache_request_headers)
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) $headers[strtolower($k)] = $v;
        }
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        if (empty($headers) && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) $headers[strtolower($k)] = $v;
        }

        // 2) Authorization puede venir por varios caminos en cPanel/Apache
        $auth =
            ($headers['authorization'] ?? null)                                 // normal
            ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null)                          // fastcgi/fpm
            ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null)                 // cPanel/Apache
            ?? ($headers['x-authorization'] ?? null);                            // plan B opcional

        if (!$auth) return null;

        // 3) Aceptar "Bearer xxx" case-insensitive y con espacios
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    // 🔹 Obtiene el token o responde 401 y corta
    private function getHeadersAndExtractToken()
    {
        $token = $this->extractBearerToken();
        if (!$token) {
            $this->sendJsonResponse([
                'status' => 'Error',
                'message' => 'Acceso no autorizado, o Token inválido.',
            ], 401);
        }
        return $token;
    }

    // 🔹 IP real por detrás de Cloudflare (útil si comparás claim "ip")
    private function getRealClientIp(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    // 🔹 (Opcional) en getUserFromToken() validá el claim "ip" si lo incluís al firmar
    protected function getUserFromToken()
    {
        try {
            $token = $this->getHeadersAndExtractToken();
            $decodedToken = $this->getJwtInstance()->validateToken($token);
            if (!is_object($decodedToken)) {
                $this->sendJsonResponse([
                    'status'  => 'Error',
                    'message' => 'Token inválido o revocado.',
                ], 403);
            }

            $user_id = $decodedToken->id ?? null;
            if (!$user_id) {
                $this->sendJsonResponse([
                    'status'  => 'Error',
                    'message' => 'Token inválido o sin ID.',
                ], 403);
            }

            // ✅ Validación IP opcional (si tu token trae "ip")
            // if (!empty($decodedToken->ip)) {
            //     $reqIp = $this->getRealClientIp();
            //     if (trim((string)$decodedToken->ip) !== $reqIp) {
            //         $this->sendJsonResponse([
            //             'status'  => 'Error',
            //             'message' => 'Token inválido por IP.',
            //         ], 403);
            //     }
            // }

            return ['id' => $user_id];
        } catch (\Throwable $e) {
            $this->sendJsonResponse([
                'status'  => 'Error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
}
