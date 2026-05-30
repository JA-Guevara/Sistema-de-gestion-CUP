<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * El token de recuperación no existe, ya fue usado, o no corresponde a ningún usuario.
 */
final class PasswordResetTokenInvalid extends \RuntimeException
{
}
