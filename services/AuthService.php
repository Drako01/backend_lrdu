<?php

declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../repositories/AuthRepository.php';
require_once __DIR__ . '/../exceptions/DatabaseUpdateException.php';
require_once __DIR__ . '/../security/jwt.security.php';
require_once __DIR__ . '/../utils/emailer.utils.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../enums/roles.enum.php';
include_once __DIR__ . '/../managers/session.mannager.php';
#endregion

final class AuthService
{
    private AuthRepository $authRepository;
    private array $messages;
    private array $config;
    private EmailSender $emailSender;

    public function __construct()
    {
        $this->authRepository = new AuthRepository();
        $this->messages = require __DIR__ . '/../utils/messages.error.php';
        $this->config   = require __DIR__ . '/../config/validate.php';
        $this->emailSender = new EmailSender();
    }

    /**
     * Registro de usuario (User minimal):
     * Campos: first_name, last_name, email, password, role?, token (autogenerado)
     */
    public function registerUser(
        string $first_name,
        string $last_name,
        string $password,
        string $email,
        ?Role $role = null,
        ?string $token = null
    ): array {
        // Validaciones de modelo
        if (!User::validatePassword($password)) {
            throw new InvalidArgumentException($this->messages['ERROR_PASSWORD'] ?? 'Password inválido.');
        }
        if (!User::validateEmail($email)) {
            throw new InvalidArgumentException($this->messages['ERROR_EMAIL_FORMAT'] ?? 'Email inválido.');
        }
        if ($this->authRepository->isEmailExists($email)) {
            throw new InvalidArgumentException($this->messages['ERROR_EMAIL_DUPLICATE'] ?? 'Email ya registrado.');
        }

        // Instancia de dominio (hashea internamente)
        $user = User::createBasic($first_name, $last_name, $password, $email);
        $user->setRole($role ?? Role::CLIENT);

        // Token inicial (ID=0 en alta; opcionalmente se regenera luego)
        $username = $first_name . ' ' . $last_name;
        $roleLabel = $user->getRole()->getDisplayName();
        $jwt = SecurityJWT::generateToken(
            0,               // id aún no persistido
            $username,
            $email,
            $roleLabel,
            SecurityJWT::getUserIp()
        );
        $user->setToken($jwt);

        try {
            // Persistir (campos mínimos)
            $created = $this->authRepository->registerUser(
                first_name: $user->getFirstName() ?? $first_name,
                last_name: $user->getLastName()  ?? $last_name,
                password: $user->getPassword(), // hash
                email: $user->getEmail(),
                role: $user->getRole(),
                token: $user->getToken()
            );

            if (!$created) {
                throw new RuntimeException($this->messages['ERROR_USER'] ?? 'No se pudo registrar el usuario.');
            }

            // (Opcional) regenerar token con ID real
            $username = ($created['first_name'] ?? $first_name) . ' ' . ($created['last_name'] ?? $last_name);
            $roleLabel = Role::tryFrom((string)$created['role'])?->getDisplayName() ?? $roleLabel;

            $finalJwt = SecurityJWT::generateToken(
                (int)$created['id'],
                $username,
                (string)$created['email'],
                $roleLabel,
                SecurityJWT::getUserIp()
            );
            $created = $this->authRepository->updateUserToken($created['email'], $finalJwt);

            return [
                'message' => "Usuario {$username} registrado con éxito.",
                'user'    => $created,
            ];
        } catch (DatabaseUpdateException $e) {
            throw new RuntimeException("Error al registrar usuario: " . $e->getMessage());
        }
    }

    /**
     * Login minimal: email + password → token nuevo + sesión PHP opcional
     */
    public function loginUser(string $email, string $password): array
    {
        $user = $this->authRepository->getUserByEmail($email);
        if (!$user || empty($user['password'])) {
            throw new InvalidArgumentException($this->messages['ERROR_LOGIN_FAILED'] ?? 'Credenciales inválidas.');
        }
        if (!password_verify($password, $user['password'])) {
            throw new InvalidArgumentException($this->messages['ERROR_INVALID_PASSWORD'] ?? 'Password incorrecto.');
        }

        $roleEnum = Role::tryFrom((string)$user['role']) ?? Role::CLIENT;
        $roleLabel = $roleEnum->getDisplayName();
        $roleNumber = $roleEnum->getDisplayNumber();
        $username  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        // Generar nuevo token
        $token = SecurityJWT::generateToken(
            (int)$user['id'],
            $username,
            (string)$user['email'],
            $roleLabel,
            SecurityJWT::getUserIp()
        );

        $updated = $this->authRepository->updateUserToken($email, $token);

        // Sesión PHP (minimal footprint)
        @session_start();
        $_SESSION['user'] = [
            'id'            => (int)$user['id'],
            'full_name'     => $username,
            'email'         => $user['email'],
            'role'          => $roleLabel,
            'role_number'   => $roleNumber,
            'role_value'    => $roleEnum->value,
            'token'         => $token,
        ];

        return [
            'user'  => $_SESSION['user'],
        ];
    }

    /**
     * Logout minimal (invalida sesión PHP; el token se deja vencer por exp)
     */
    public function logoutUser(): array
    {
        try {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            $token = $authHeader ? str_replace('Bearer ', '', $authHeader) : null;

            if ($token) {
                $securityJWT  = new SecurityJWT();
                $decodedToken = $securityJWT->validateToken($token);

                // Si viene error igual revocamos/limpiamos para mayor seguridad
                SecurityJWT::invalidateToken($token);

                // Si tenés el token guardado en DB, lo dejamos en NULL
                if (
                    method_exists($this->authRepository, 'getUserByToken') &&
                    method_exists($this->authRepository, 'updateUserToken')
                ) {
                    $user = $this->authRepository->getUserByToken($token);
                    if ($user && isset($user['email'])) {
                        $this->authRepository->updateUserToken($user['email'], null);
                    }
                }
            }

            SessionManager::destroySession();

            return ['status' => 'Success', 'message' => 'Cierre de sesión exitoso.'];
        } catch (Throwable $e) {
            return ['status' => 'Error', 'message' => 'Error Interno del Servidor. ' . $e->getMessage()];
        }
    }



    public function validateToken(?string $token): bool
    {
        try {
            if ($token === null) return false;
            $decoded = SecurityJWT::decodeToken($token);
            return isset($decoded->exp) && $decoded->exp > time();
        } catch (Throwable) {
            return false;
        }
    }

    public function getTokenByUserId(int $userId): ?string
    {
        return $this->authRepository->getTokenByUserId($userId);
    }

    #region EMAILS
    /* ===== Recuperación / Activación (adaptado a modelo minimal) ===== */

    public function sendConfirmationEmail(string $email, string $token): void
    {
        // Demo/placeholder de activación vía email (sin columna active/email_verified)
        $user = $this->authRepository->getUserByToken($token);
        $username = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
        $this->emailSender->sendActivateAccountEmailSuccess($email, $username);
        // No hay cambio en DB (modelo minimal no tiene "active/email_verified")
    }

    public function sendConfirmationEmailCode(string $email, string $username, string $code, string $token): void
    {
        $this->emailSender->sendActivateAccountEmailCodeSuccess($email, $username, $code);
        // Sin update en DB (modelo minimal)
    }

    public function activateUser(string $token): array
    {
        if (!$token) return ['error' => 'Token no proporcionado.'];

        $user = $this->authRepository->getUserByToken($token);
        if (!$user) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }
        // Sin columna "active": confirmamos sin update
        return ['message' => 'Cuenta validada correctamente. Ya podés iniciar sesión.'];
    }

    public function recoveryPassword(string $email): array
    {
        $user = $this->authRepository->getUserByEmail($email);
        if (!$user) {
            throw new InvalidArgumentException('El email no está registrado.');
        }
        $this->sendRecoveryEmail($email);
        return ['message' => 'Se envió un enlace de recuperación a tu correo.'];
    }

    public function sendRecoveryEmail(string $email): array
    {
        $user = $this->authRepository->getUserByEmail($email);
        if (!$user) {
            throw new InvalidArgumentException('El correo electrónico proporcionado no está registrado.');
        }

        $token = (string)$user['token'];
        $securityJWT  = new SecurityJWT();
        $decodedToken = $securityJWT->validateToken($token);
        if (!$decodedToken) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }

        // Mantener token/emitir uno nuevo sería mejor; por ahora reusamos
        $recoveryLink = rtrim($this->config['URL_SERVER'] ?? '', '/') . "/reset-password.php?token={$token}";
        $this->emailSender->sendRecoveryEmailByRecoveryLink($email, $recoveryLink);

        return ['message' => 'Enviamos un correo con instrucciones para restablecer tu contraseña.'];
    }

    public function sendSuccessEmail(string $token): array
    {
        $user = $this->authRepository->getUserByToken($token);
        $securityJWT = new SecurityJWT();
        $decoded = $securityJWT->validateToken($token);
        if (!$decoded) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }

        $username = $decoded->username ?? '';
        $this->emailSender->sendActivateAccountEmailSuccess((string)$user['email'], $username);

        return ['message' => 'Se envió confirmación por email.'];
    }

    public function recoveryPasswordByToken(string $token): array
    {
        $securityJWT = new SecurityJWT();
        $decoded = $securityJWT->validateToken($token);
        if (!$decoded) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }
        $username = $decoded->username ?? '';
        return ['message' => "{$username}, tu solicitud de recuperación es válida."];
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }

        $securityJWT = new SecurityJWT();
        $decoded = $securityJWT->validateToken($token);
        if (!$decoded) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }

        $userId = (int)($decoded->id ?? 0);
        if ($userId <= 0) {
            throw new InvalidArgumentException('Token inválido: ID no presente.');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        if ($this->authRepository->updateUserPassword($userId, $hashed)) {
            $email = $this->authRepository->getUserByToken($token)['email'] ?? '';
            $username = $decoded->username ?? '';
            $this->emailSender->sendNewPasswordEmailSuccess((string)$email, (string)$username);
        }

        return ['message' => 'Tu contraseña ha sido actualizada con éxito.'];
    }

    /* ===== Métodos secundarios (limpiados) ===== */
    #endregion

}
