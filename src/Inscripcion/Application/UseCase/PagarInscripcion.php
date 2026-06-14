<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Pago (simulado) del estudiante sobre su propia postulacion VALIDADA. El pago
 * confirma la inscripcion y dispara la asignacion del rol Estudiante.
 */
final readonly class PagarInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private ConfirmarInscripcion $confirmar,
    ) {
    }

    public function execute(int $inscripcionId, int $actorUserId): void
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->user->id !== $actorUserId) {
            throw new InscripcionException('No puedes pagar una postulacion que no es tuya.');
        }

        if (!$inscripcion->esEstudiante()) {
            throw new InscripcionException('El pago solo aplica a postulaciones de estudiante.');
        }

        // El estudiante actua como su propio actor; ConfirmarInscripcion valida
        // el estado VALIDADA y la unicidad por CI + tipo.
        $this->confirmar->execute($inscripcionId, $actorUserId);
    }
}
