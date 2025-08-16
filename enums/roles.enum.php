<?php

/**
 * Enum para los roles de usuario
 */
enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN_ROLE'; // Unico Administrador con Total acceso del sistema
    case ADMIN = 'ADMIN_ROLE';          // Administrador del sistema con acceso casi total
    case DEV = 'DEV_ROLE';              // Desarrollador con acceso a funciones técnicas
    case CLIENT = 'CLIENT_ROLE';            // Jugador regular con acceso a funciones estándar
    case SELLER = 'SELLER_ROLE';              // Moderador con permisos para gestionar jugadores
    case SUPPORT = 'SUPPORT_ROLE';      // Soporte técnico o atención al cliente

    /**
     * Obtener todos los roles
     * 
     * @return array
     */
    public static function getRoles(): array
    {
        return [
            self::SUPERADMIN,
            self::ADMIN,
            self::DEV,
            self::CLIENT,
            self::SELLER,
            self::SUPPORT,
        ];
    }

    /**
     * Validar si un rol es válido
     * 
     * @param string $role
     * @return bool
     */
    public static function isValidRole($role): bool
    {
        return in_array($role, self::getRoles(), true);
    }

    public static function isVerifyRole($role): bool
    {
        return in_array($role, array_map(fn($role) => $role->value, self::getRoles()), true);
    }

    /**
     * Obtener el nombre de visualización de un rol
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Super Administrador',
            self::ADMIN => 'Administrador',
            self::DEV => 'Desarrollador',
            self::SELLER => 'Vendedor',
            self::SUPPORT => 'Soporte',
            self::CLIENT => 'Cliente',
        };
    }

    /**
     * Obtener el Numero de visualización de un rol
     * 
     * @return int
     */
    public function getDisplayNumber(): int
    {
        return match ($this) {
            self::SUPERADMIN => 10,
            self::ADMIN => 9,
            self::DEV => 8,
            self::SELLER => 3,
            self::SUPPORT => 2,
            self::CLIENT => 1,
        };
    }

    /**
     * Obtener el nombre de visualización a partir del valor del rol (string)
     * 
     * @param string $value
     * @return string|null
     */
    public static function getDisplayNameFromValue(string $value): ?string
    {
        foreach (self::getRoles() as $role) {
            if ($role->value === $value) {
                return $role->getDisplayName();
            }
        }
        return null; // o podés lanzar una excepción si preferís
    }

    /**
     * Obtener el Role a partir del número de visualización
     * 
     * @param int $number
     * @return Role|null
     */
    public static function fromDisplayNumber(int $number): ?self
    {
        foreach (self::getRoles() as $role) {
            if ($role->getDisplayNumber() === $number) {
                return $role;
            }
        }
        return null; // o lanzar una excepción si el número no coincide
    }

    public static function tryFromName(string $name): ?self
    {
        foreach (self::getRoles() as $role) {
            if (strtoupper($role->name) === $name) {
                return $role;
            }
        }
        return null;
    }
}
