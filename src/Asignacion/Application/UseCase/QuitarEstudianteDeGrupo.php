<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Bitacora\Application\EventLog\NotasEvents;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

/**
 * Quita a un estudiante de un grupo COMPLETO: borra sus filas de las 4 materias
 * en ese grupo.
 */
final readonly class QuitarEstudianteDeGrupo
{
    public function __construct(
        private AsignacionGrupoRepository $asignaciones,
        private InscripcionRepository $inscripciones,
        private NotasEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, int $grupoId, ?int $actorUserId): void
    {
        $filas = $this->asignaciones->listByInscripcionAndGrupo($inscripcionId, $grupoId);
        if ($filas === []) {
            throw new NotaException('El estudiante no esta asignado a ese grupo.');
        }

        $grupo = $filas[0]->grupo->codigo;
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        $estudiante = $inscripcion !== null
            ? trim($inscripcion->apellidos . ' ' . $inscripcion->nombres)
            : '#' . $inscripcionId;

        $this->asignaciones->removeMany($filas);

        $this->events->estudianteDesasignado('Todas las materias', $grupo, $estudiante, $actorUserId);
    }
}
