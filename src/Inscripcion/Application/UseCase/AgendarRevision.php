<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class AgendarRevision
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, \DateTimeImmutable $fecha, ?int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        $inscripcion->agendarRevision($fecha);
        $this->inscripciones->save($inscripcion);

        $this->events->revisionAgendada($inscripcion->ci, $fecha, $actorUserId);
    }
}
