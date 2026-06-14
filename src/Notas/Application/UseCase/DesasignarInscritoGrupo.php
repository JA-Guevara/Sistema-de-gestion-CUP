<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Bitacora\Application\EventLog\NotasEvents;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

final readonly class DesasignarInscritoGrupo
{
    public function __construct(
        private AsignacionGrupoRepository $asignaciones,
        private NotasEvents $events,
    ) {
    }

    public function execute(int $asignacionId, ?int $actorUserId): void
    {
        $asignacion = $this->asignaciones->findById($asignacionId);
        if ($asignacion === null) {
            throw new NotaException('La asignacion no existe.');
        }

        $materia = sprintf('%s - %s', $asignacion->materia->codigo, $asignacion->materia->nombre);
        $grupo = $asignacion->grupo->codigo;
        $estudiante = trim($asignacion->inscripcion->apellidos . ' ' . $asignacion->inscripcion->nombres);

        $this->asignaciones->remove($asignacion);

        $this->events->estudianteDesasignado($materia, $grupo, $estudiante, $actorUserId);
    }
}
