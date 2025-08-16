<?php

/**
 * Excepción personalizada para manejar casos en los que no se puede eliminar un administrador.
 */
class AdminException extends Exception
{
    /**
     * @var string Nombre de usuario del administrador.
     */
    private $username;

    /**
     * Constructor de la excepción personalizada.
     *
     * @param string $username Nombre de usuario del administrador.
     * @param string $message Mensaje de error personalizado (opcional).
     * @param int $code Código de error (opcional, predeterminado a 403).
     * @param Throwable|null $previous Excepción anterior para encadenamiento (opcional).
     */
    public function __construct(
        string $username,
        string $message = "",
        int $code = 403,
        Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = "No se puede eliminar a '{$username}', porque tiene rol de Administrador.";
        }
        $this->username = $username;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Obtiene el nombre de usuario del administrador.
     *
     * @return string Nombre de usuario.
     */
    public function getUserName(): string
    {
        return $this->username;
    }
}
