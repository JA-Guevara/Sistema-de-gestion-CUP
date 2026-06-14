<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * El admin rechaza/descarta la solicitud de anulacion del postulante; la
 * postulacion sigue en su estado actual.
 */
final readonly class RechazarSolicitudAnulacion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, ?int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if (!$inscripcion->anulacionSolicitada) {
            throw new InscripcionException('No hay una solicitud de anulacion pendiente para esta postulacion.');
        }

        $inscripcion->rechazarSolicitudAnulacion();
        $this->inscripciones->flush();

        $this->events->solicitudAnulacionRechazada($inscripcion->ci, $actorUserId);
    }
}
