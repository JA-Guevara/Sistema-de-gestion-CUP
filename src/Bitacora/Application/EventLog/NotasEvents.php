<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/**
 * Eventos de auditoria del dominio de evaluacion:
 * - Asignaciones (docente↔materia/grupo y estudiante↔grupo) se registran bajo
 *   el modulo "Asignaciones".
 * - El registro de calificaciones se registra bajo el modulo "Notas".
 */
final readonly class NotasEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function docenteAsignado(string $materia, string $grupo, string $docente, ?int $actorUserId): void
    {
        $this->audit->assigned(
            ModuleCatalog::ASIGNACIONES,
            sprintf('%s / Grupo %s', $materia, $grupo),
            sprintf('Asigno al docente %s a %s (grupo %s).', $docente, $materia, $grupo),
            $actorUserId,
        );
    }

    public function docenteDesasignado(string $materia, string $grupo, string $docente, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::ASIGNACIONES,
            ActionCatalog::UNASSIGN,
            sprintf('Quito al docente %s de %s (grupo %s).', $docente, $materia, $grupo),
            entity: sprintf('%s / Grupo %s', $materia, $grupo),
            userId: $actorUserId,
        );
    }

    public function estudiantesAsignados(string $materia, string $grupo, int $cantidad, ?int $actorUserId): void
    {
        $this->audit->assigned(
            ModuleCatalog::ASIGNACIONES,
            sprintf('%s / Grupo %s', $materia, $grupo),
            sprintf('Asigno %d estudiante(s) al grupo %s en %s.', $cantidad, $grupo, $materia),
            $actorUserId,
        );
    }

    public function estudianteDesasignado(string $materia, string $grupo, string $estudiante, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::ASIGNACIONES,
            ActionCatalog::UNASSIGN,
            sprintf('Quito a %s del grupo %s en %s.', $estudiante, $grupo, $materia),
            entity: sprintf('%s / Grupo %s', $materia, $grupo),
            userId: $actorUserId,
        );
    }

    public function notasRegistradas(string $materia, string $grupo, int $cantidad, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::NOTAS,
            ActionCatalog::UPDATE,
            sprintf('Registro o actualizo %d nota(s) de %s (grupo %s).', $cantidad, $materia, $grupo),
            entity: sprintf('%s / Grupo %s', $materia, $grupo),
            userId: $actorUserId,
        );
    }
}
