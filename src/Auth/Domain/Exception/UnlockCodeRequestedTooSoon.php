<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

/**
 * Se intentó reenviar el código de desbloqueo antes de cumplir el cooldown
 * (5 min desde el último envío). Defensa contra spam de emails.
 */
final class UnlockCodeRequestedTooSoon extends \RuntimeException
{
}
