<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Retomar un borrador: el postulante presenta su propia pre-inscripcion en
 * estado BORRADOR (BORRADOR -> PRESENTADA).
 */
final readonly class PresentarInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->user->id !== $actorUserId) {
            throw new InscripcionException('No puedes presentar una postulacion que no es tuya.');
        }

        if (!$inscripcion->isBorrador()) {
            throw new InscripcionException('Solo puedes presentar una postulacion en borrador.');
        }

        if ($inscripcion->esEstudiante() && $inscripcion->carrera === null) {
            throw new InscripcionException('Selecciona una carrera antes de presentar (edita el borrador).');
        }

        if ($inscripcion->esDocente() && ($inscripcion->docenteProfesion === null || trim($inscripcion->docenteProfesion) === '')) {
            throw new InscripcionException('Indica tu profesion o area antes de presentar (edita el borrador).');
        }

        $inscripcion->presentar();
        $this->inscripciones->flush();

        $this->events->presentada($inscripcion->ci, $actorUserId);
    }
}
