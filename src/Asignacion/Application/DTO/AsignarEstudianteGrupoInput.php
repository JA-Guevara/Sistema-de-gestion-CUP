<?php

declare(strict_types=1);

namespace App\Asignacion\Application\DTO;

/**
 * Asignacion de estudiantes a un grupo COMPLETO. Cada estudiante queda
 * matriculado en las 4 materias activas del grupo. Sirve para el flujo
 * individual (1 id) y el masivo (N ids).
 */
final readonly class AsignarEstudianteGrupoInput
{
    /** @param list<int> $inscripcionIds */
    public function __construct(
        public int $grupoId,
        public array $inscripcionIds,
        public ?int $actorUserId,
    ) {
    }
}
