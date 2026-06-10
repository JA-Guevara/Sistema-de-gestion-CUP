<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Catalog;

final class TipoPeriodo
{
    public const INSCRIPCION = 'INSCRIPCION';
    public const VALIDACION_DOCUMENTAL = 'VALIDACION_DOCUMENTAL';
    public const ASIGNACION_GRUPOS = 'ASIGNACION_GRUPOS';
    public const CLASES = 'CLASES';
    public const EVALUACION = 'EVALUACION';
    public const PUBLICACION_RESULTADOS = 'PUBLICACION_RESULTADOS';

    private const LABELS = [
        self::INSCRIPCION => 'Inscripcion',
        self::VALIDACION_DOCUMENTAL => 'Validacion documental',
        self::ASIGNACION_GRUPOS => 'Asignacion de grupos',
        self::CLASES => 'Clases',
        self::EVALUACION => 'Evaluacion',
        self::PUBLICACION_RESULTADOS => 'Publicacion de resultados',
    ];

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
