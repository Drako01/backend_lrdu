<?php
declare(strict_types=1);

#region Imports
require_once __DIR__ . '/../enums/roles.enum.php'; // enum Role
#endregion

final class User implements JsonSerializable
{
    #region Atributos (solo los requeridos)
    private int $id;
    private string $password;
    private string $email;
    private Role $role;
    private ?string $token;
    private ?string $first_name;
    private ?string $last_name;
    private ?string $connected_at;
    private ?string $disconnected_at;
    private ?string $created_at;
    #endregion

    #region Constructor
    public function __construct(
        int $id,
        string $password,
        string $email,
        Role $role = Role::CLIENT,
        ?string $first_name = null,
        ?string $last_name = null,
        ?string $token = null,
        ?string $connected_at = null,
        ?string $disconnected_at = null,
        ?string $created_at = null
    ) {
        $this->setId($id);
        $this->setPassword($password);     // Hash interno (bcrypt)
        $this->setEmail($email);
        $this->setRole($role);
        $this->setFirstName($first_name);
        $this->setLastName($last_name);
        $this->setToken($token);
        $this->setConnectedAt($connected_at);
        $this->setDisconnectedAt($disconnected_at);
        $this->setCreatedAt($created_at);
    }

    public static function createBasic(string $first_name, string $last_name, string $password, string $email): self
    {
        return new self(
            id: 0,
            password: $password,
            email: $email,
            role: Role::CLIENT,
            first_name: $first_name,
            last_name: $last_name,
            created_at: date('Y-m-d H:i:s')
        );
    }
    #endregion

    #region Getters / Setters
    public function getId(): int { return $this->id; }
    public function setId(int $id): void {
        if ($id < 0) throw new InvalidArgumentException('El id no puede ser negativo.');
        $this->id = $id;
    }

    public function getPassword(): string { return $this->password; }
    /** Recibe contraseña en claro y la hashea con bcrypt */
    public function setPassword(string $password): void {
        if (!self::validatePassword($password)) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }
    /** Set directo del hash (útil para hydratar desde DB) */
    public function setPasswordHash(string $hash): void {
        if (!str_starts_with($hash, '$2y$') && !str_starts_with($hash, '$2a$')) {
            throw new InvalidArgumentException('Hash de contraseña inválido.');
        }
        $this->password = $hash;
    }
    public function verifyPassword(string $plain): bool {
        return password_verify($plain, $this->password);
    }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void {
        $email = trim($email);
        if (!self::validateEmail($email)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }
        $this->email = $email;
    }

    public function getRole(): Role { return $this->role; }
    public function setRole(Role $role): void { $this->role = $role; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(?string $token): void { $this->token = $token? trim($token) : null; }

    public function getFirstName(): ?string { return $this->first_name; }
    public function setFirstName(?string $first_name): void {
        $this->first_name = self::optName($first_name);
    }

    public function getLastName(): ?string { return $this->last_name; }
    public function setLastName(?string $last_name): void {
        $this->last_name = self::optName($last_name);
    }

    public function getConnectedAt(): ?string { return $this->connected_at; }
    public function setConnectedAt(?string $connected_at): void {
        $this->connected_at = self::optTs($connected_at);
    }

    public function getDisconnectedAt(): ?string { return $this->disconnected_at; }
    public function setDisconnectedAt(?string $disconnected_at): void {
        $this->disconnected_at = self::optTs($disconnected_at);
    }

    public function getCreatedAt(): ?string { return $this->created_at; }
    public function setCreatedAt(?string $created_at): void {
        $this->created_at = self::optTs($created_at);
    }
    #endregion

    #region (De)serialización
    /** Acepta snake_case y camelCase; role admite instancia o valor del enum */
    public static function fromArray(array $data): self
    {
        $get = fn(array $a, string $snake, string $camel, mixed $default = null)
            => $a[$snake] ?? $a[$camel] ?? $default;

        $roleVal = $get($data, 'role', 'role', Role::CLIENT);
        $role = $roleVal instanceof Role
            ? $roleVal
            : (is_string($roleVal) ? Role::from($roleVal) : Role::CLIENT);

        $user = new self(
            id: (int)($get($data, 'id', 'id', 0)),
            password: (string)$get($data, 'password', 'password', ''), // se hashea en setPassword
            email: (string)$get($data, 'email', 'email', ''),
            role: $role,
            first_name: self::optStr($get($data, 'first_name', 'firstName')),
            last_name: self::optStr($get($data, 'last_name', 'lastName')),
            token: self::optStr($get($data, 'token', 'token')),
            connected_at: self::optStr($get($data, 'connected_at', 'connectedAt')),
            disconnected_at: self::optStr($get($data, 'disconnected_at', 'disconnectedAt')),
            created_at: self::optStr($get($data, 'created_at', 'createdAt'))
        );

        // Si viene ya hasheado desde DB, permitimos set directo:
        if (isset($data['password_hash']) || isset($data['passwordHash'])) {
            $hash = (string)($data['password_hash'] ?? $data['passwordHash']);
            $user->setPasswordHash($hash);
        }

        return $user;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'password'         => $this->password, // hash
            'email'            => $this->email,
            'role'             => $this->role->value,
            'token'            => $this->token,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'connected_at'     => $this->connected_at,
            'disconnected_at'  => $this->disconnected_at,
            'created_at'       => $this->created_at,
        ];
    }

    public function jsonSerialize(): array { return $this->toArray(); }

    public function toJson(int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this, $options);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
        }
        return self::fromArray($data);
    }
    #endregion

    #region Utils
    public static function validatePassword(string $password): bool
    {
        return strlen($password) >= 8;
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateName(string $name): bool
    {
        return (bool)preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-\'\.]{2,50}$/u', $name);
    }

    private static function optStr(mixed $v): ?string
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    private static function optTs(?string $ts): ?string
    {
        // Si querés ser más estricto: validar formato 'Y-m-d H:i:s'
        return self::optStr($ts);
    }

    private static function optName(?string $name): ?string
    {
        $name = self::optStr($name);
        if ($name === null) return null;
        if (!self::validateName($name)) {
            throw new InvalidArgumentException('El nombre/apellido contiene caracteres inválidos o longitud incorrecta.');
        }
        return $name;
    }
    #endregion

    public function __toString(): string
    {
        $full = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        $label = $full !== '' ? $full : 'Usuario';
        return sprintf('%s (ID: %d, Email: %s)', $label, $this->id, $this->email);
    }
}
