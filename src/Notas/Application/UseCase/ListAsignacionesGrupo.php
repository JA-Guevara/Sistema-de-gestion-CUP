<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Notas\Domain\Entity\AsignacionGrupo;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

final readonly class ListAsignacionesGrupo
{
    public function __construct(private AsignacionGrupoRepository $asignaciones)
    {
    }

    /** @return list<AsignacionGrupo> */
    public function execute(int $materiaId, int $gestionId): array
    {
        return $this->asignaciones->listByMateriaAndGestion($materiaId, $gestionId);
    }
}
