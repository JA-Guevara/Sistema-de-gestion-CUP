<?php

declare(strict_types=1);

namespace App\Notas\Application\DTO;

final readonly class AsignarGrupoInput
{
    /**
     * @param list<int> $inscripcionIds Inscritos a asignar al grupo en esa materia
     */
    public function __construct(
        public int $materiaId,
        public int $grupoId,
        public array $inscripcionIds,
        public ?int $actorUserId,
    ) {
    }
}
