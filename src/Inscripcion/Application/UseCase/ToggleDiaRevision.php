<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;

/** Habilita o deshabilita un dia de revision (sin borrarlo). */
final readonly class ToggleDiaRevision
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

        $dia->habilitado = !$dia->habilitado;
        $this->calendario->save($dia);

        $this->events->calendarioRevisionActualizado(
            $dia->gestion->codigo,
            sprintf('%s el dia de revision %s', $dia->habilitado ? 'Habilito' : 'Deshabilito', $dia->fecha->format('d/m/Y')),
            $actorUserId,
        );
    }
}
