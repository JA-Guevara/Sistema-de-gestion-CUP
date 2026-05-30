<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * La cuenta del usuario está bloqueada por intentos fallidos de login.
 * El usuario debe usar el código de desbloqueo enviado por email.
 */
final class AccountLocked extends \RuntimeException
{
}
