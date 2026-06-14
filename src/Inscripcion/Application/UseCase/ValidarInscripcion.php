<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class ValidarInscripcion
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

        if (!$inscripcion->isPresentada()) {
            throw new InscripcionException('Solo puedes aprobar una postulacion presentada (no un borrador ni una ya resuelta).');
        }

        if (!$inscripcion->todosAprobados()) {
            throw new InscripcionException('Aprueba todos los documentos del checklist antes de aprobar la postulacion.');
        }

        $inscripcion->validar($actorUserId);
        $this->inscripciones->save($inscripcion);

        $this->events->validada($inscripcion->ci, $actorUserId);
    }
}
