<?php

return [
    // Errores relacionados con usuarios
    'ERROR_USERNAME' => "El nombre de usuario debe tener al menos 4 caracteres.",
    'ERROR_USERNAME_TOO_LONG' => "El nombre de usuario no puede tener más de 12 caracteres.",
    'ERROR_EMAIL' => "El correo electrónico no es válido.",
    'ERROR_EMAIL_FORMAT' => "El formato del correo electrónico no es válido. Por favor, verifica y vuelve a intentarlo.",
    'ERROR_EMAIL_DUPLICATE' => "El correo ya está en uso por otro usuario.",
    'ERROR_EMAIL_USERNAME_DUPLICATE' => "El correo o el Username ya están en uso por otro usuario.",
    'ERROR_PASSWORD' => "La contraseña debe tener al menos 8 caracteres.",
    'ERROR_USERNAME_DUPLICATE' => "El nombre de usuario ya está en uso por otro usuario.",
    'ERROR_USER' => "No se pudo crear el usuario. Intenta de nuevo.",
    'ERROR_PASSWORD_MISMATCH' => "La nueva contraseña no coincide con la confirmación.",
    'ERROR_INVALID_PASSWORD' => "La contraseña proporcionada es inválida.",
    'ERROR_INVALID_CODE' => "El código proporcionado es inválido.",
    
    
    // Errores generales
    'ERROR_NOT_FOUND' => "El recurso solicitado no se encontró.",
    'ERROR_INVALID_ID' => "El ID proporcionado no es válido.",
    'ERROR_UNAUTHORIZED' => "No tienes permisos para realizar esta acción.",
    'ERROR_INVALID_INPUT' => "Los datos proporcionados no son válidos.",
    'ERROR_MISSING_PARAMETER' => "Faltan parámetros necesarios para completar la operación.",

    // Errores relacionados con roles
    'ERROR_INVALID_ROLE' => "El rol especificado no es válido.",
    'ERROR_ROLE_REQUIRED' => "Se requiere un rol para esta operación.",

    // Errores de autenticación
    'ERROR_LOGIN_FAILED' => "Nombre de usuario o contraseña incorrectos.",
    'ERROR_TOKEN_EXPIRED' => "Tu sesión ha expirado. Por favor, inicia sesión nuevamente.",
    'ERROR_TOKEN_INVALID' => "El token proporcionado no es válido.",
    'ERROR_INSUFFICIENT_PRIVILEGES' => "No tienes los privilegios suficientes para realizar esta operación.",
    'ERROR_ACCOUNT_LOCKED' => "Tu cuenta está bloqueada debido a múltiples intentos fallidos de inicio de sesión.",
    'ERROR_PASSWORD_TOO_WEAK' => "La contraseña es demasiado débil, debe contener al menos un número y un carácter especial.",
    'ERROR_TERMS_AND_CONDITIONS' => "Debes aceptar los términos y condiciones para continuar",

    //Errores de envio de Email para Activacion de cuenta
    'ERROR_REGISTER_FAILED' => "No se pudo enviar el Email para Activar tu Cuenta, Por favor reintenta más tarde.!",

    // Erroes de Servidor    
    'ERROR_SERVER' => "Ocurrió un error en el servidor. Intenta de nuevo más tarde.",
    'SERVER_ERROR' => "Ocurrió un error en el servidor. Intenta de nuevo más tarde.",
    'INTERNAL_SERVER_ERROR' => "Error interno del Servidor, aguarde unos Instantes...",
    'CONTROLLER_SERVER_ERROR' => "Ocurrió un error en el controlador: ",
];
