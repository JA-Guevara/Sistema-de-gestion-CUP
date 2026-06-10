<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Catalog;

final class EstadoGestion
{
    public const BORRADOR = 'BORRADOR';
    public const CONFIGURACION = 'CONFIGURACION';
    public const ABIERTA_INSCRIPCION = 'ABIERTA_INSCRIPCION';
    public const VALIDACION_DOCUMENTAL = 'VALIDACION_DOCUMENTAL';
    public const ASIGNACION_GRUPOS = 'ASIGNACION_GRUPOS';
    public const EN_CLASES = 'EN_CLASES';
    public const EN_EVALUACION = 'EN_EVALUACION';
    public const PUBLICACION_RESULTADOS = 'PUBLICACION_RESULTADOS';
    public const FINALIZADA = 'FINALIZADA';
    public const CANCELADA = 'CANCELADA';

    private const LABELS = [
        self::BORRADOR => 'Borrador',
        self::CONFIGURACION => 'Configuracion',
        self::ABIERTA_INSCRIPCION => 'Inscripcion abierta',
        self::VALIDACION_DOCUMENTAL => 'Validacion documental',
        self::ASIGNACION_GRUPOS => 'Asignacion de grupos',
        self::EN_CLASES => 'En clases',
        self::EN_EVALUACION => 'En evaluacion',
        self::PUBLICACION_RESULTADOS => 'Publicacion de resultados',
        self::FINALIZADA => 'Finalizada',
        self::CANCELADA => 'Cancelada',
    ];

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
