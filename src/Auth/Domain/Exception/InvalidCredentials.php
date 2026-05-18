<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * Las credenciales enviadas al login no corresponden a ningún usuario.
 */
final class InvalidCredentials extends \RuntimeException
{
}
