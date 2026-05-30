<?php

declare(strict_types=1);

namespace App\Bitacora\Domain\Catalog;

/**
 * Catálogo central de módulos del sistema.
 *
 * Cuando cada módulo emite logs, usa estas constantes para identificar
 * a qué área pertenece el evento.
 */
final class ModuleCatalog
{
    public const AUTH = 'Autenticación';
    public const POSTULANTES = 'Postulantes';
    public const EXAMENES = 'Exámenes';
    public const GRUPOS = 'Asignación de Grupos';
    public const REPORTES = 'Reportes';
    public const ADMIN = 'Panel Administrativo';
    public const BITACORA = 'Bitácora';

    /** Lista todos los módulos (para el filtro del UI). */
    public static function all(): array
    {
        return [
            self::AUTH,
            self::POSTULANTES,
            self::EXAMENES,
            self::GRUPOS,
            self::REPORTES,
            self::ADMIN,
            self::BITACORA,
        ];
    }
}
