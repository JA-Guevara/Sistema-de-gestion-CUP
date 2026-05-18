<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * Los datos enviados al registro no pasan las validaciones de formato.
 * Ej.: email mal formado, nombre vacío, contraseña corta.
 */
final class InvalidRegistrationData extends \InvalidArgumentException
{
}
