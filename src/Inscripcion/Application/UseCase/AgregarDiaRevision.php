<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Entity\CalendarioRevision;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;

/** Agrega un dia habil de revision (fecha + capacidad) a una gestion. */
final readonly class AgregarDiaRevision
{
    public function __construct(
        private GestionRepository $gestiones,
        private CalendarioRevisionRepository $calendario,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $gestionId, \DateTimeImmutable $fecha, int $capacidad, ?int $actorUserId): void
    {
        $gestion = $this->gestiones->findById($gestionId);
        if ($gestion === null) {
            throw new InscripcionException('La gestion no existe.');
        }
        if ($capacidad < 1) {
            throw new InscripcionException('La capacidad debe ser al menos 1.');
        }

        $fecha = $fecha->setTime(0, 0);
        if ($this->calendario->findByGestionAndFecha($gestionId, $fecha) !== null) {
            throw new InscripcionException('Ya existe un dia de revision para esa fecha.');
        }

        $this->calendario->save(new CalendarioRevision($gestion, $fecha, $capacidad));

        $this->events->calendarioRevisionActualizado(
            $gestion->codigo,
            sprintf('Agrego el dia de revision %s (capacidad %d)', $fecha->format('d/m/Y'), $capacidad),
            $actorUserId,
        );
    }
}
