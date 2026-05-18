<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * El email del intento de registro ya pertenece a un usuario existente.
 */
final class EmailAlreadyRegistered extends \RuntimeException
{
}
