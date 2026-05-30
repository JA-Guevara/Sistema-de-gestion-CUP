<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * El código de desbloqueo no coincide, el email no existe o la cuenta no está bloqueada.
 */
final class UnlockCodeInvalid extends \RuntimeException
{
}
