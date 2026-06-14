<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

/**
 * Estado de cada requisito en el acta de control de recepcion presencial.
 * - PENDIENTE: aun no se reviso.
 * - ENTREGADO: el postulante lo presento y esta conforme.
 * - OBSERVADO: lo presento pero tiene un problema (ver observacion).
 * - FALTA: no lo presento.
 */
final class EstadoVerificacion
{
    public const PENDIENTE = 'PENDIENTE';
    public const ENTREGADO = 'ENTREGADO';
    public const OBSERVADO = 'OBSERVADO';
    public const FALTA = 'FALTA';

    private const LABELS = [
        self::PENDIENTE => 'Pendiente',
        self::ENTREGADO => 'Entregado',
        self::OBSERVADO => 'Observado',
        self::FALTA => 'No presento',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $estado): string
    {
        return self::LABELS[$estado] ?? $estado;
    }

    public static function isValid(string $estado): bool
    {
        return in_array($estado, self::all(), true);
    }
}
