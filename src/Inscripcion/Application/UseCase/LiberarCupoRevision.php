<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;

/**
 * Libera (decrementa) el cupo del dia de revision que tenia asignado una
 * postulacion, al anularla o rechazarla. Opera sobre una Inscripcion cargada y
 * NO hace flush: el caso de uso que anula/rechaza persiste en el mismo flush.
 */
final readonly class LiberarCupoRevision
{
    public function __construct(private CalendarioRevisionRepository $calendario)
    {
    }

    public function execute(Inscripcion $inscripcion): void
    {
        if ($inscripcion->fechaPresentacionDocs === null || $inscripcion->gestion->id === null) {
            return;
        }

        $dia = $this->calendario->findByGestionAndFecha(
            (int) $inscripcion->gestion->id,
            $inscripcion->fechaPresentacionDocs,
        );
        if ($dia !== null && $dia->agendados > 0) {
            $dia->agendados--;
        }
    }
}
