<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;

/** Elimina un dia de revision (solo si no tiene revisiones agendadas). */
final readonly class EliminarDiaRevision
{
    public function __construct(
        private CalendarioRevisionRepository $calendario,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $diaId, ?int $actorUserId): void
    {
        $dia = $this->calendario->findById($diaId);
        if ($dia === null) {
            throw new InscripcionException('El dia de revision no existe.');
        }
        if ($dia->agendados > 0) {
            throw new InscripcionException('No puedes eliminar un dia con revisiones ya agendadas. Deshabilitalo para no agendar mas.');
        }

        $codigo = $dia->gestion->codigo;
        $fechaStr = $dia->fecha->format('d/m/Y');
        $this->calendario->remove($dia);

        $this->events->calendarioRevisionActualizado($codigo, sprintf('Elimino el dia de revision %s', $fechaStr), $actorUserId);
    }
}
