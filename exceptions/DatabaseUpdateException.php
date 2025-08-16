<?php
class DatabaseUpdateException extends Exception
{
    public function __construct(
        $message = "Error al actualizar la base de datos.", 
        $code = 500, 
        Throwable $previous = null
        )
    {
        parent::__construct($message, $code, $previous);
    }
}
