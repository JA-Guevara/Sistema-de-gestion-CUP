<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

/**
 * Estados del ciclo de vida de un pago de inscripcion.
 *
 * PENDIENTE  -> se creo la sesion de Checkout, esperando que el postulante pague.
 * PAGADO     -> Stripe confirmo el pago (checkout.session.completed / payment_status=paid).
 * CANCELADO  -> el postulante abandono el Checkout (cancel_url).
 * FALLIDO    -> el pago fue rechazado o fallo.
 * EXPIRADO   -> la sesion de Checkout caduco sin pagarse.
 */
final class EstadoPago
{
    public const PENDIENTE = 'PENDIENTE';
    public const PAGADO = 'PAGADO';
    public const CANCELADO = 'CANCELADO';
    public const FALLIDO = 'FALLIDO';
    public const EXPIRADO = 'EXPIRADO';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PENDIENTE,
            self::PAGADO,
            self::CANCELADO,
            self::FALLIDO,
            self::EXPIRADO,
        ];
    }
}
