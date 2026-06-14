<?php

declare(strict_types=1);

namespace App\Bitacora\Domain\Catalog;

final class ModuleCatalog
{
    public const AUTH = 'Autenticacion';
    public const USUARIOS = 'Usuarios';
    public const ROLES = 'Roles y Permisos';
    public const POSTULANTES = 'Postulantes';
    public const INSCRIPCIONES = 'Inscripciones';
    public const EXAMENES = 'Examenes';
    public const GRUPOS = 'Asignacion de Grupos';
    public const TURNOS = 'Turnos';
    public const CRONOGRAMAS = 'Cronogramas';
    public const REPORTES = 'Reportes';
    public const ADMIN = 'Panel Administrativo';
    public const CONFIGURACION = 'Configuracion';
    public const GESTIONES_CUP = 'Gestiones CUP';
    public const CARRERAS = 'Carreras';
    public const MATERIAS = 'Materias';
    public const AULAS = 'Aulas';
    public const HORARIOS = 'Horarios';
    public const NOTAS = 'Notas';
    public const ASIGNACIONES = 'Asignaciones';
    public const BITACORA = 'Bitacora';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::AUTH,
            self::USUARIOS,
            self::ROLES,
            self::POSTULANTES,
            self::INSCRIPCIONES,
            self::EXAMENES,
            self::GRUPOS,
            self::TURNOS,
            self::CRONOGRAMAS,
            self::REPORTES,
            self::ADMIN,
            self::CONFIGURACION,
            self::GESTIONES_CUP,
            self::CARRERAS,
            self::MATERIAS,
            self::AULAS,
            self::HORARIOS,
            self::NOTAS,
            self::ASIGNACIONES,
            self::BITACORA,
        ];
    }
}
