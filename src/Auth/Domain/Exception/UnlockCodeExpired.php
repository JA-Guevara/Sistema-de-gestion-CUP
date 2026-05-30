<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * El código de desbloqueo expiró (más de 1 hora desde que se emitió).
 * El usuario puede solicitar uno nuevo desde el form de unlock.
 */
final class UnlockCodeExpired extends \RuntimeException
{
}
