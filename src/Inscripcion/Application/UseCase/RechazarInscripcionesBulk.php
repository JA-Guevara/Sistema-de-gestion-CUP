<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Application\DTO\RechazarInscripcionesBulkInput;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Rechazo masivo: marca como RECHAZADA las postulaciones seleccionadas (salvo
 * las que ya estan rechazadas). Un unico flush para todo el lote.
 */
final readonly class RechazarInscripcionesBulk
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(RechazarInscripcionesBulkInput $input): int
    {
        $seleccionadas = $this->inscripciones->findByIds($input->ids);
        if ($seleccionadas === []) {
            throw new InscripcionException('Selecciona al menos una postulacion.');
        }

        /** @var list<Inscripcion> $rechazadas */
        $rechazadas = [];
        foreach ($seleccionadas as $inscripcion) {
            if ($inscripcion->isRechazada()) {
                continue;
            }

            $inscripcion->rechazar($input->motivo, $input->actorUserId);
            $rechazadas[] = $inscripcion;
        }

        if ($rechazadas !== []) {
            $this->inscripciones->saveMany($rechazadas);
            foreach ($rechazadas as $inscripcion) {
                $this->events->rechazada($inscripcion->ci, $input->motivo, $input->actorUserId);
            }
        }

        return count($rechazadas);
    }
}
