<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de una regla académica (año cerrado, porcentajes incompletos, ...).
 * Se muestra al usuario como mensaje y se vuelve a la página anterior.
 */
class ReglaNegocioException extends RuntimeException
{
}
