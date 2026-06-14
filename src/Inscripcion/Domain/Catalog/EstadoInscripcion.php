<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

final class EstadoInscripcion
{
    public const BORRADOR = 'BORRADOR';
    public const PRESENTADA = 'PRESENTADA';
    public const VALIDADA = 'VALIDADA';
    public const CONFIRMADA = 'CONFIRMADA';
    public const RECHAZADA = 'RECHAZADA';
    public const ANULADA = 'ANULADA';
    // Estados heredados (compatibilidad con datos/flujo previo).
    public const PENDIENTE = 'PENDIENTE';
    public const COMPLETADA = 'COMPLETADA';

    private const LABELS = [
        self::BORRADOR => 'Borrador',
        self::PRESENTADA => 'Presentada',
        self::VALIDADA => 'Validada',
        self::CONFIRMADA => 'Confirmada',
        self::RECHAZADA => 'Rechazada',
        self::ANULADA => 'Anulada',
        self::PENDIENTE => 'Pendiente',
        self::COMPLETADA => 'Completada',
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
