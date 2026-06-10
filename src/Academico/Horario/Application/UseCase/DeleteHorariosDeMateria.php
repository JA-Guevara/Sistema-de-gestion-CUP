<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\EventLog\HorarioEvents;

/**
 * Elimina todas las franjas de una materia dentro de un grupo (en todos sus dias).
 */
final readonly class DeleteHorariosDeMateria
{
    public function __construct(
        private HorarioRepository $horarios,
        private GrupoRepository $grupos,
        private MateriaRepository $materias,
        private HorarioEvents $events,
    ) {
    }

    public function execute(int $grupoId, int $materiaId, ?int $actorUserId): int
    {
        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null) {
            throw new HorarioException('El grupo seleccionado no existe.');
        }

        $materia = $this->materias->findById($materiaId);
        if ($materia === null) {
            throw new HorarioException('La materia seleccionada no existe.');
        }

        $eliminados = $this->horarios->deleteByGrupoAndMateria($grupoId, $materiaId);

        $this->events->eliminadaMateria($materia->nombre, $grupo->codigo, $eliminados, $actorUserId);

        return $eliminados;
    }
}
