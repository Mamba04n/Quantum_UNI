<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Throwable;

final class UserMessage
{
    public static function error(Throwable $exception, string $fallback = 'No se pudo completar la acción. Intenta de nuevo.'): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();
            if (is_string($message) && ! preg_match('/SQLSTATE|Stack trace|vendor[\\\\\/]|PDOException|password[=:]|token[=:]/i', $message)) {
                return $message;
            }
        }

        return $fallback;
    }

    public static function http(int $status): string
    {
        return match ($status) {
            401 => 'Inicia sesión para continuar.',
            403 => 'No tienes permiso para esta acción.',
            404 => 'No encontramos esta página. Revisa el enlace.',
            405 => 'Esta dirección no permite esa acción. Abre el módulo e intenta de nuevo.',
            419 => 'Tu sesión venció. Actualiza la página e inicia sesión si se solicita.',
            422 => 'Revisa los campos indicados.',
            429 => 'Hay demasiados intentos. Espera un momento e intenta de nuevo.',
            503 => 'El sistema está en mantenimiento. Intenta más tarde.',
            default => 'No se pudo completar la acción. Intenta de nuevo.',
        };
    }
}
