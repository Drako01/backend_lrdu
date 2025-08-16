<?php

class VerifyParamsHelper
{

    public static function validateNumericParams(int|string ...$params): bool
    {
        foreach ($params as $index => $param) {
            if (is_int($param) || (is_string($param) && ctype_digit($param))) {
                continue; // Es válido, seguimos con el siguiente
            }

            ResponseHelper::error([
                'error' => "El parámetro #" . ($index + 1) . " debe ser un número entero válido.",
            ], 400);

            return false;
        }

        return true; // Si todos son válidos, retornamos true
    }

    public static function verifyParam(mixed ...$params): void
    {
        foreach ($params as $index => $param) {
            if (is_null($param)) {
                ResponseHelper::error(["Error" => "El parámetro #" . ($index + 1) . " es requerido."], 400);
                exit;
            }
        }
    }
}
