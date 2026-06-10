<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Bitacora\Application\EventLog\HorarioEvents;

/**
 * Elimina todo el horario semanal de un grupo en una sola operacion.
 */
final readonly class DeleteHorariosDeGrupo
{
    public function __construct(
        private HorarioRepository $horarios,
        private GrupoRepository $grupos,
        private HorarioEvents $events,
    ) {
    }

    public function execute(int $grupoId, ?int $actorUserId): int
    {
        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null) {
            throw new HorarioException('El grupo seleccionado no existe.');
        }

        $eliminados = $this->horarios->deleteByGrupo($grupoId);

        $this->events->eliminadoGrupo($grupo->codigo, $eliminados, $actorUserId);

        return $eliminados;
    }
}
