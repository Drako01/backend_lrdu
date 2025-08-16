<?php

class DatabaseTypeNotSupportedException extends Exception
{
    public function __construct(
                $message = "No existe este tipo de Base de Datos", $code = 400, 
                Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
