<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * El token de recuperación venció (más de 30 min desde que se emitió).
 */
final class PasswordResetTokenExpired extends \RuntimeException
{
}
