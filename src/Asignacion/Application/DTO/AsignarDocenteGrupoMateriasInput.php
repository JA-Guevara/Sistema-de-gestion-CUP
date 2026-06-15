<?php

declare(strict_types=1);

namespace App\Asignacion\Application\DTO;

/**
 * Un docente dicta VARIAS materias dentro de UN mismo grupo. Asignar varias
 * materias en un grupo no incrementa el conteo de grupos (limite de 4 grupos
 * distintos por docente).
 */
final readonly class AsignarDocenteGrupoMateriasInput
{
    /** @param list<int> $materiaIds */
    public function __construct(
        public int $docenteId,
        public int $grupoId,
        public array $materiaIds,
        public ?int $actorUserId,
    ) {
    }
}
