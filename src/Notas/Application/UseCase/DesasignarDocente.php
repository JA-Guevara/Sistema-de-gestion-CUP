<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Bitacora\Application\EventLog\NotasEvents;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;

final readonly class DesasignarDocente
{
    public function __construct(
        private AsignacionDocenteRepository $asignaciones,
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
        $docente = trim($asignacion->docente->firstName . ' ' . $asignacion->docente->lastName);

        $this->asignaciones->remove($asignacion);

        $this->events->docenteDesasignado($materia, $grupo, $docente, $actorUserId);
    }
}
