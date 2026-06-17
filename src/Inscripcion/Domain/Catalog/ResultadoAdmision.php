<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

/**
 * Resultado de la Admision Final del estudiante (adjudicacion de carrera por
 * promedio respetando cupos). Es independiente del estado de la inscripcion.
 */
final class ResultadoAdmision
{
    public const ADMITIDO_PRIMERA = 'ADMITIDO_PRIMERA';
    public const ADMITIDO_SEGUNDA = 'ADMITIDO_SEGUNDA';
    public const LISTA_ESPERA = 'LISTA_ESPERA';
    public const REPROBADO = 'REPROBADO';
    public const PENDIENTE = 'PENDIENTE';

    /** @var array<string,string> */
    private const LABELS = [
        self::ADMITIDO_PRIMERA => 'Admitido (1ra opcion)',
        self::ADMITIDO_SEGUNDA => 'Admitido (2da opcion)',
        self::LISTA_ESPERA => 'Lista de espera',
        self::REPROBADO => 'Reprobado',
        self::PENDIENTE => 'Pendiente (notas incompletas)',
    ];

    public static function label(?string $code): string
    {
        if ($code === null) {
            return 'Sin procesar';
        }

        return self::LABELS[$code] ?? $code;
    }

    public static function esAdmitido(?string $code): bool
    {
        return $code === self::ADMITIDO_PRIMERA || $code === self::ADMITIDO_SEGUNDA;
    }
}
