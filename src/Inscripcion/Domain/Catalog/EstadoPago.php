<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

/**
 * Estados del ciclo de vida de un pago de inscripcion.
 *
 * PENDIENTE  -> se creo la sesion de Checkout, esperando que el postulante pague.
 * PAGADO     -> el pago fue confirmado (Stripe payment_status=paid, o registro manual).
 * OBSERVADO  -> el admin marco el pago con una observacion (revision/duda).
 * ANULADO    -> el admin anulo el pago (no cuenta como recaudacion).
 * CANCELADO  -> el postulante abandono el Checkout (cancel_url).
 * FALLIDO    -> el pago fue rechazado o fallo.
 * EXPIRADO   -> la sesion de Checkout caduco sin pagarse.
 */
final class EstadoPago
{
    public const PENDIENTE = 'PENDIENTE';
    public const PAGADO = 'PAGADO';
    public const OBSERVADO = 'OBSERVADO';
    public const ANULADO = 'ANULADO';
    public const CANCELADO = 'CANCELADO';
    public const FALLIDO = 'FALLIDO';
    public const EXPIRADO = 'EXPIRADO';

    private const LABELS = [
        self::PENDIENTE => 'Pendiente',
        self::PAGADO => 'Pagado',
        self::OBSERVADO => 'Observado',
        self::ANULADO => 'Anulado',
        self::CANCELADO => 'Cancelado',
        self::FALLIDO => 'Fallido',
        self::EXPIRADO => 'Expirado',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * Estados que el admin puede fijar manualmente desde el panel de pagos.
     *
     * @return list<string>
     */
    public static function editablesAdmin(): array
    {
        return [self::PENDIENTE, self::PAGADO, self::OBSERVADO, self::ANULADO];
    }

    public static function isEditableAdmin(string $estado): bool
    {
        return in_array($estado, self::editablesAdmin(), true);
    }

    public static function label(string $estado): string
    {
        return self::LABELS[$estado] ?? $estado;
    }
}
