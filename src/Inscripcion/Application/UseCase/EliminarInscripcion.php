<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Gestion\Domain\Entity\CupoGestion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class EliminarInscripcion
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
            throw new InscripcionException('Inscripcion no encontrada.');
        }

        $ci = $inscripcion->ci;
        $nombre = $inscripcion->nombres . ' ' . $inscripcion->apellidos;

        $gestion = $inscripcion->gestion;
        $cupo = $gestion->cupos->first();
        if ($cupo instanceof CupoGestion) {
            $cupo->inscritos = max(0, $cupo->inscritos - 1);
            $cupo->disponibles = $cupo->disponibles + 1;
        }

        $this->inscripciones->remove($inscripcion);
        $this->events->eliminada($ci, $nombre, $actorUserId);
    }
}
