<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

final class EstadoDocumento
{
    public const PENDIENTE = 'PENDIENTE';
    public const APROBADO = 'APROBADO';
    public const OBSERVADO = 'OBSERVADO';

    private const LABELS = [
        self::PENDIENTE => 'Pendiente',
        self::APROBADO => 'Aprobado',
        self::OBSERVADO => 'Observado',
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
