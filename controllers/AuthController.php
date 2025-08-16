<?php
declare(strict_types=1);

#region imports
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../enums/roles.enum.php'; // enum Role
#endregion

/**
 * AuthController — alineado al modelo User minimal y al enum Role provisto.
 */
final class AuthController
{
    private AuthService $authService;
    private array $messages;

    public function __construct(?AuthService $authService = null)
    {
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        try {
            $this->authService = $authService ?? new AuthService();
        } catch (Throwable $e) {
            ResponseHelper::serverError('Auth init error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Registro: first_name, last_name, email, password, role (opcional)
     */
    public function register(?array $params = null): void
    {
        $data = $params ?? json_decode(file_get_contents('php://input'), true) ?? [];

        $first  = trim((string)($data['first_name'] ?? ''));
        $last   = trim((string)($data['last_name'] ?? ''));
        $email  = trim((string)($data['email'] ?? ''));
        $pass   = (string)($data['password'] ?? '');
        $roleIn = $data['role'] ?? null;

        if ($first === '' || $last === '' || $email === '' || $pass === '') {
            ResponseHelper::error($this->messages['ERROR_MISSING_PARAMETER'] ?? 'Parámetros faltantes.', 400);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('Email inválido.', 400);
            return;
        }
        if (strlen($pass) < 8) {
            ResponseHelper::error('La contraseña debe tener al menos 8 caracteres.', 400);
            return;
        }

        // ✅ Parseo de rol usando tu enum
        $role = $this->parseRole($roleIn);

        try {
            // Contrato esperado del servicio:
            // registerUser(string $first, string $last, string $password, string $email, Role $role, ?string $token = null): array
            $res = $this->authService->registerUser($first, $last, $pass, $email, $role, null);

            if (!$res || !isset($res['user'])) {
                ResponseHelper::error($this->messages['ERROR_EMAIL_USERNAME_DUPLICATE'] ?? 'Usuario ya existe.', 409);
                return;
            }

            $u = $res['user'];

            // Si el servicio devuelve el value del enum (p.ej. ADMIN_ROLE), lo convertimos a display
            $roleValue = $u['role'] ?? $role->value;
            $roleEnum  = Role::tryFrom((string)$roleValue) ?? $role;
            $roleLabel = $roleEnum->getDisplayName();

            $payload = [
                'id'          => (int)($u['id'] ?? 0),
                'name'        => trim(($u['first_name'] ?? $first) . ' ' . ($u['last_name'] ?? $last)),
                'email'       => $u['email'] ?? $email,
                'role'        => $roleLabel,          // para UI
                'role_value'  => $roleEnum->value,    // para persistencia (ej: ADMIN_ROLE)
            ];

            ResponseHelper::success($payload, 201, 'user');
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    /**
     * Login: email + password
     */
    public function login(?array $params = null): void
    {
        $data = $params ?? json_decode(file_get_contents('php://input'), true) ?? [];

        $email = trim((string)($data['email'] ?? ''));
        $pass  = (string)($data['password'] ?? '');

        if ($email === '' || $pass === '') {
            ResponseHelper::error($this->messages['ERROR_MISSING_PARAMETER'] ?? 'Parámetros faltantes.', 400);
            return;
        }

        try {
            $result = $this->authService->loginUser($email, $pass);
            ResponseHelper::loguinSuccess($result, 200); // mantengo tu helper
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 401);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        try {
            $ok = $this->authService->logoutUser();
            if (!$ok) {
                ResponseHelper::error('Hubo un error al cerrar la sesión.', 404);
                return;
            }
            ResponseHelper::success('Sesión cerrada correctamente.', 200);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    public function activateUser(string $token): void
    {
        try {
            $ok = $this->authService->activateUser($token);
            if ($ok) {
                if (method_exists($this->authService, 'sendSuccessEmail')) {
                    $this->authService->sendSuccessEmail($token);
                }
                ResponseHelper::success(['message' => 'Cuenta activada.'], 200);
            } else {
                ResponseHelper::error($this->messages['ERROR_TOKEN_INVALID'] ?? 'Token inválido.', 403);
            }
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    public function recoveryPassword(array $params): void
    {
        $email = trim((string)($params['email'] ?? ''));
        if ($email === '') {
            ResponseHelper::error(['El email es requerido.'], 400);
            return;
        }

        try {
            $res = $this->authService->recoveryPassword($email);
            ResponseHelper::success($res, 200);
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    public function recoveryPasswordByToken(string $token): void
    {
        try {
            $res = $this->authService->recoveryPasswordByToken($token);
            ResponseHelper::success($res, 200);
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    public function resetPassword(array $queryParams): void
    {
        $token = $queryParams['token'] ?? null;
        $pass  = $queryParams['password'] ?? null;

        if (!$token) { ResponseHelper::error(['El token es requerido.'], 400); return; }
        if (!$pass)  { ResponseHelper::error(['El password es requerido.'], 400); return; }
        if (strlen((string)$pass) < 8) { ResponseHelper::error(['Password demasiado corto.'], 400); return; }

        try {
            $ok = $this->authService->resetPassword((string)$token, (string)$pass);
            if (!$ok) {
                ResponseHelper::error($this->messages['ERROR_TOKEN_INVALID'] ?? 'Token inválido.', 403);
                return;
            }
            ResponseHelper::success(['message' => 'Password actualizado.'], 200);
        } catch (InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            ResponseHelper::serverError(($this->messages['ERROR_SERVER'] ?? 'Error del servidor: ') . $e->getMessage(), 500);
        }
    }

    /**
     * Parseo de rol resiliente según tu enum:
     * - 10/9/8/3/2/1 → fromDisplayNumber
     * - 'ADMIN' → tryFromName
     * - 'ADMIN_ROLE' → tryFrom (valor del enum)
     * - 'Administrador' → display name
     */
    private function parseRole(mixed $input): Role
    {
        if ($input instanceof Role) return $input;

        // Números o strings numéricos -> display number
        if (is_int($input) || (is_string($input) && ctype_digit($input))) {
            $r = Role::fromDisplayNumber((int)$input);
            if ($r !== null) return $r;
        }

        if (is_string($input)) {
            $s = trim($input);

            // Valor del enum (e.g., 'ADMIN_ROLE')
            if ($r = Role::tryFrom($s)) return $r;

            // Nombre del case (e.g., 'ADMIN')
            if ($r = Role::tryFromName(strtoupper($s))) return $r;

            // Display name (e.g., 'Administrador', 'Cliente')
            foreach (Role::getRoles() as $case) {
                if (strcasecmp($case->getDisplayName(), $s) === 0) {
                    return $case;
                }
            }
        }

        // Fallback razonable
        return Role::CLIENT;
    }
}
