<?php

declare(strict_types=1);

namespace App\Asignacion\Application\DTO;

/**
 * Asignacion individual de un docente a una materia dentro de un grupo.
 */
final readonly class AsignarDocenteInput
{
    public function __construct(
        public int $materiaId,
        public int $grupoId,
        public int $docenteId,
        public ?int $actorUserId,
    ) {
    }
}
