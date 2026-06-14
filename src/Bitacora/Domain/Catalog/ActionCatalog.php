<?php

declare(strict_types=1);

namespace App\Bitacora\Domain\Catalog;

/**
 * Catálogo central de acciones registrables en la bitácora.
 *
 * - Constantes públicas que los demás módulos referencian (nunca strings sueltos).
 * - Nivel de severidad por acción (INFO / ALERTA / CRITICO).
 * - Mapeo a clase de color para badges UI.
 *
 * Para agregar una acción nueva: sumarla acá + en LEVELS + en LABELS.
 */
final class ActionCatalog
{
    // ---------- Acciones ----------

    public const LOGIN = 'LOGIN';
    public const LOGOUT = 'LOGOUT';
    public const LOGIN_FAILED = 'LOGIN_FAILED';
    public const REGISTER = 'REGISTER';
    public const PASSWORD_RESET_REQUESTED = 'PASSWORD_RESET_REQUESTED';
    public const PASSWORD_RESET_COMPLETED = 'PASSWORD_RESET_COMPLETED';
    public const ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    public const ACCOUNT_UNLOCKED = 'ACCOUNT_UNLOCKED';

    public const CREATE = 'CREATE';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';
    public const GENERATE = 'GENERATE';
    public const REGENERATE = 'REGENERATE';
    public const ACTIVATE = 'ACTIVATE';
    public const DEACTIVATE = 'DEACTIVATE';
    public const STATE_CHANGE = 'STATE_CHANGE';
    public const ASSIGN = 'ASSIGN';
    public const UNASSIGN = 'UNASSIGN';
    public const REPROGRAM = 'REPROGRAM';
    public const IMPORT = 'IMPORT';
    public const VIEW = 'VIEW';
    public const DOWNLOAD = 'DOWNLOAD';
    public const APPROVE = 'APPROVE';
    public const REJECT = 'REJECT';
    public const EXPORT = 'EXPORT';
    public const PAYMENT = 'PAYMENT';
    public const ERROR = 'ERROR';

    // ---------- Niveles de severidad ----------

    public const LEVEL_INFO = 'INFO';
    public const LEVEL_ALERTA = 'ALERTA';
    public const LEVEL_CRITICO = 'CRITICO';

    /** Acción → nivel de severidad. */
    private const LEVELS = [
        self::LOGIN => self::LEVEL_INFO,
        self::LOGOUT => self::LEVEL_INFO,
        self::LOGIN_FAILED => self::LEVEL_ALERTA,
        self::REGISTER => self::LEVEL_INFO,
        self::PASSWORD_RESET_REQUESTED => self::LEVEL_INFO,
        self::PASSWORD_RESET_COMPLETED => self::LEVEL_INFO,
        self::ACCOUNT_LOCKED => self::LEVEL_ALERTA,
        self::ACCOUNT_UNLOCKED => self::LEVEL_INFO,
        self::CREATE => self::LEVEL_INFO,
        self::UPDATE => self::LEVEL_INFO,
        self::DELETE => self::LEVEL_ALERTA,
        self::GENERATE => self::LEVEL_INFO,
        self::REGENERATE => self::LEVEL_INFO,
        self::ACTIVATE => self::LEVEL_INFO,
        self::DEACTIVATE => self::LEVEL_ALERTA,
        self::STATE_CHANGE => self::LEVEL_INFO,
        self::ASSIGN => self::LEVEL_INFO,
        self::UNASSIGN => self::LEVEL_ALERTA,
        self::REPROGRAM => self::LEVEL_INFO,
        self::IMPORT => self::LEVEL_INFO,
        self::VIEW => self::LEVEL_INFO,
        self::DOWNLOAD => self::LEVEL_INFO,
        self::APPROVE => self::LEVEL_INFO,
        self::REJECT => self::LEVEL_ALERTA,
        self::EXPORT => self::LEVEL_INFO,
        self::PAYMENT => self::LEVEL_INFO,
        self::ERROR => self::LEVEL_CRITICO,
    ];

    /** Etiqueta legible para mostrar en la UI. */
    private const LABELS = [
        self::LOGIN => 'LOGIN',
        self::LOGOUT => 'LOGOUT',
        self::LOGIN_FAILED => 'FALLO',
        self::REGISTER => 'REGISTRO',
        self::PASSWORD_RESET_REQUESTED => 'RESET',
        self::PASSWORD_RESET_COMPLETED => 'RESET OK',
        self::ACCOUNT_LOCKED => 'BLOQUEO',
        self::ACCOUNT_UNLOCKED => 'DESBLOQ.',
        self::CREATE => 'CREAR',
        self::UPDATE => 'EDITAR',
        self::DELETE => 'ELIMINAR',
        self::GENERATE => 'GENERAR',
        self::REGENERATE => 'REGENERAR',
        self::ACTIVATE => 'ACTIVAR',
        self::DEACTIVATE => 'DESACTIVAR',
        self::STATE_CHANGE => 'CAMBIO ESTADO',
        self::ASSIGN => 'ASIGNAR',
        self::UNASSIGN => 'DESASIGNAR',
        self::REPROGRAM => 'REPROGRAMAR',
        self::IMPORT => 'IMPORTAR',
        self::VIEW => 'VER',
        self::DOWNLOAD => 'DESCARGAR',
        self::APPROVE => 'APROBAR',
        self::REJECT => 'RECHAZAR',
        self::EXPORT => 'EXPORTAR',
        self::PAYMENT => 'PAGO',
        self::ERROR => 'ERROR',
    ];

    public static function level(string $action): string
    {
        return self::LEVELS[$action] ?? self::LEVEL_INFO;
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /** Lista todas las acciones (para el filtro del UI). */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }
}
