<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Mailer;

use Symfony\Component\Mime\Address;

/**
 * Resuelve el remitente (From) de los correos del sistema.
 *
 * Gmail SMTP exige que el From sea la cuenta autenticada. Si MAIL_FROM esta
 * vacio o usa el dominio ficticio ".local" (que NO tiene SPF/DKIM y por eso
 * cae en spam o lo rechazan los proveedores externos), se usa el usuario SMTP
 * real (MAIL_USER). Asi evitamos el caso tipico de "a unos llega y a otros no".
 */
final class SenderAddress
{
    public static function resolve(): Address
    {
        $configured = trim((string) ($_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: ''));
        $authUser = trim((string) ($_ENV['MAIL_USER'] ?? getenv('MAIL_USER') ?: ''));
        $name = (string) ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'CUP FICCT');

        $address = ($configured !== '' && !str_ends_with($configured, '.local'))
            ? $configured
            : ($authUser !== '' ? $authUser : 'noreply@cup-ficct.local');

        return new Address($address, $name);
    }
}
