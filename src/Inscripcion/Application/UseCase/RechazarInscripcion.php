<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class RechazarInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private LiberarCupoRevision $liberarCupoRevision,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, ?string $motivo, ?int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        // Libera el cupo del dia de revision que tenia asignado (si lo tenia).
        $this->liberarCupoRevision->execute($inscripcion);

        $inscripcion->rechazar($motivo, $actorUserId);
        $this->inscripciones->save($inscripcion);

        $this->events->rechazada($inscripcion->ci, $motivo, $actorUserId);
    }
}
