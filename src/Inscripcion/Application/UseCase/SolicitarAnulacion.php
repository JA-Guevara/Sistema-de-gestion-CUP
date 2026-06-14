<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * El postulante solicita anular su postulacion ya validada o confirmada. No la
 * anula directamente: deja una solicitud que el admin resuelve en Admisiones.
 */
final readonly class SolicitarAnulacion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $inscripcionId, int $actorUserId, ?string $motivo): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->user->id !== $actorUserId) {
            throw new InscripcionException('No puedes solicitar anular una postulacion que no es tuya.');
        }

        if (!in_array($inscripcion->estado, [EstadoInscripcion::VALIDADA, EstadoInscripcion::CONFIRMADA], true)) {
            throw new InscripcionException('Solo puedes solicitar la anulacion de una postulacion validada o confirmada.');
        }

        $inscripcion->solicitarAnulacion($motivo);
        $this->inscripciones->flush();

        $this->events->anulacionSolicitada($inscripcion->ci, $motivo, $actorUserId);
    }
}
