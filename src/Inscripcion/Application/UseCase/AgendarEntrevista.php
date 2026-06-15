<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Agenda la entrevista de una postulacion de docente ya validada.
 */
final readonly class AgendarEntrevista
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

        if (!$inscripcion->esDocente()) {
            throw new InscripcionException('La entrevista solo aplica a postulaciones de docente.');
        }

        if (!$inscripcion->isValidada()) {
            throw new InscripcionException('Agenda la entrevista despues de validar la documentacion.');
        }

        if ($fecha <= new \DateTimeImmutable()) {
            throw new InscripcionException('La fecha de la entrevista debe ser futura.');
        }

        if ($inscripcion->fechaPresentacionDocs !== null && $fecha < $inscripcion->fechaPresentacionDocs) {
            throw new InscripcionException('La entrevista no puede ser anterior a la revision de documentos.');
        }

        $inscripcion->agendarEntrevista($fecha);
        $this->inscripciones->flush();

        $this->events->entrevistaAgendada($inscripcion->ci, $fecha, $actorUserId);
    }
}
