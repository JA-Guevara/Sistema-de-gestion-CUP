<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;

/**
 * Asigna automaticamente la cita de revision de documentos al primer dia habil
 * con cupo de la gestion. Opera sobre una Inscripcion ya cargada y NO hace
 * flush: el caso de uso que presenta (PresentarInscripcion / CrearInscripcion)
 * persiste todo en el mismo flush, por lo que el incremento de "agendados"
 * queda en la misma transaccion.
 *
 * Devuelve la fecha/hora asignada, o null si no hay dias habiles con cupo
 * (degradacion elegante: la postulacion se presenta sin cita y el admin puede
 * agendar manualmente).
 */
final readonly class AgendarRevisionAutomatica
{
    /** Hora fija de la cita dentro del dia asignado. */
    private const HORA_CITA = 8;

    public function __construct(private CalendarioRevisionRepository $calendario)
    {
    }

    public function execute(Inscripcion $inscripcion): ?\DateTimeImmutable
    {
        if ($inscripcion->gestion->id === null) {
            return null;
        }

        $dia = $this->calendario->findPrimerDiaDisponible(
            (int) $inscripcion->gestion->id,
            new \DateTimeImmutable('today'),
        );
        if ($dia === null) {
            return null;
        }

        $dia->agendados++;
        $cita = $dia->fecha->setTime(self::HORA_CITA, 0);
        $inscripcion->agendarRevision($cita);

        return $cita;
    }
}
