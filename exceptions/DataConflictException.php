<?php

class DataConflictException extends Exception
{
    public function __construct(
        string $message = "Los datos ya existen.", 
        int $code = 409, 
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}