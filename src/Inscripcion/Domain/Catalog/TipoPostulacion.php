<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

final class TipoPostulacion
{
    public const ESTUDIANTE = 'ESTUDIANTE';
    public const DOCENTE = 'DOCENTE';

    private const LABELS = [
        self::ESTUDIANTE => 'Estudiante (postular al CUP)',
        self::DOCENTE => 'Docente (dictar en el CUP)',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $tipo): string
    {
        return self::LABELS[$tipo] ?? $tipo;
    }

    public static function isValid(string $tipo): bool
    {
        return in_array($tipo, self::all(), true);
    }
}
