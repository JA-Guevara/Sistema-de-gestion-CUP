<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/**
 * EventLog del módulo Horarios.
 *
 * Centraliza el "qué decir" de cada evento de horarios para trazabilidad.
 * Los use cases inyectan esta clase (no RecordLogEntry directo) y llaman
 * a un método semántico. Mismo patrón que AuthEvents.
 */
final readonly class HorarioEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function generadoMasivo(string $grupoCodigo, int $cantidad, bool $reemplazo, ?int $actorUserId): void
    {
        $this->audit->generated(
            ModuleCatalog::HORARIOS,
            sprintf('Grupo %s', $grupoCodigo),
            sprintf('Generó automáticamente %d horarios para el grupo %s%s.', $cantidad, $grupoCodigo, $reemplazo ? ' (reemplazando los existentes)' : ''),
            userId: $actorUserId,
            extra: ['cantidad' => $cantidad],
        );
    }

    public function generadoMultiGrupo(string $estrategia, int $cantidad, int $grupos, ?int $actorUserId): void
    {
        $this->audit->generated(
            ModuleCatalog::HORARIOS,
            sprintf('%d grupos', $grupos),
            sprintf('Generación multi-grupo (%s): %d horarios en %d grupos.', $estrategia, $cantidad, $grupos),
            userId: $actorUserId,
            extra: ['estrategia' => $estrategia, 'cantidad' => $cantidad, 'grupos' => $grupos],
        );
    }

    public function creado(string $grupoCodigo, string $materia, string $dia, ?int $actorUserId): void
    {
        $this->audit->created(
            ModuleCatalog::HORARIOS,
            sprintf('Grupo %s', $grupoCodigo),
            sprintf('Creó horario para el grupo %s, materia %s, día %s.', $grupoCodigo, $materia, $dia),
            userId: $actorUserId,
        );
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function modificado(string $grupoCodigo, array $antes, array $despues, ?int $actorUserId): void
    {
        $this->audit->updated(
            ModuleCatalog::HORARIOS,
            sprintf('Grupo %s', $grupoCodigo),
            sprintf('Modificó un horario del grupo %s.', $grupoCodigo),
            before: $antes,
            after: $despues,
            userId: $actorUserId,
        );
    }

    public function eliminadaFranja(string $grupoCodigo, string $materia, string $dia, string $rango, ?int $actorUserId): void
    {
        $this->audit->deleted(
            ModuleCatalog::HORARIOS,
            sprintf('Grupo %s', $grupoCodigo),
            sprintf('Eliminó horario del grupo %s, materia %s, día %s (%s).', $grupoCodigo, $materia, $dia, $rango),
            userId: $actorUserId,
        );
    }

    public function eliminadoGrupo(string $grupoCodigo, int $cantidad, ?int $actorUserId): void
    {
        $this->audit->deleted(
            ModuleCatalog::HORARIOS,
            sprintf('Grupo %s', $grupoCodigo),
            sprintf('Eliminó el horario completo del grupo %s (%d franjas).', $grupoCodigo, $cantidad),
            userId: $actorUserId,
            before: ['franjas' => $cantidad],
        );
    }

    public function eliminadosGrupos(int $grupos, int $cantidad, ?int $actorUserId): void
    {
        $this->audit->deleted(
            ModuleCatalog::HORARIOS,
            sprintf('%d grupos', $grupos),
            sprintf('Elimino horarios de forma masiva en %d grupos (%d franjas).', $grupos, $cantidad),
            userId: $actorUserId,
            before: ['grupos' => $grupos, 'franjas' => $cantidad],
        );
    }

    public function eliminadaMateria(string $materia, string $grupoCodigo, int $cantidad, ?int $actorUserId): void
    {
        $this->audit->deleted(
            ModuleCatalog::HORARIOS,
            sprintf('Grupo %s', $grupoCodigo),
            sprintf('Eliminó la materia %s del horario del grupo %s (%d franjas).', $materia, $grupoCodigo, $cantidad),
            userId: $actorUserId,
            before: ['materia' => $materia, 'franjas' => $cantidad],
        );
    }
}
