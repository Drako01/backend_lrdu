<?php

class NotFoundException extends Exception
{
    public function __construct(
        string $message = "Recurso no encontrado", 
        int $code = 404, 
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
