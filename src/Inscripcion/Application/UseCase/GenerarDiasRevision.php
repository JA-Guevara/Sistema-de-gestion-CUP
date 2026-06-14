<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Entity\CalendarioRevision;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;

/**
 * Genera dias habiles de revision en un rango de fechas, con la misma
 * capacidad. Opcionalmente solo dias de semana (L-V). Salta las fechas que ya
 * existen para no duplicar. Devuelve cuantos dias se crearon.
 */
final readonly class GenerarDiasRevision
{
    private const TOPE_DIAS = 366;

    public function __construct(
        private GestionRepository $gestiones,
        private CalendarioRevisionRepository $calendario,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $gestionId, \DateTimeImmutable $desde, \DateTimeImmutable $hasta, int $capacidad, bool $soloLaborables, ?int $actorUserId): int
    {
        $gestion = $this->gestiones->findById($gestionId);
        if ($gestion === null) {
            throw new InscripcionException('La gestion no existe.');
        }
        if ($capacidad < 1) {
            throw new InscripcionException('La capacidad debe ser al menos 1.');
        }

        $desde = $desde->setTime(0, 0);
        $hasta = $hasta->setTime(0, 0);
        if ($hasta < $desde) {
            throw new InscripcionException('El rango de fechas no es valido (la fecha final es anterior a la inicial).');
        }

        $existentes = [];
        foreach ($this->calendario->listByGestion($gestionId) as $dia) {
            $existentes[$dia->fecha->format('Y-m-d')] = true;
        }

        /** @var list<CalendarioRevision> $nuevos */
        $nuevos = [];
        $cursor = $desde;
        $guarda = 0;
        while ($cursor <= $hasta && $guarda < self::TOPE_DIAS) {
            $guarda++;
            $clave = $cursor->format('Y-m-d');
            $esLaborable = (int) $cursor->format('N') <= 5;
            if ((!$soloLaborables || $esLaborable) && !isset($existentes[$clave])) {
                $nuevos[] = new CalendarioRevision($gestion, $cursor, $capacidad);
                $existentes[$clave] = true;
            }
            $cursor = $cursor->modify('+1 day');
        }

        if ($nuevos !== []) {
            $this->calendario->saveMany($nuevos);
            $this->events->calendarioRevisionActualizado(
                $gestion->codigo,
                sprintf(
                    'Genero %d dia(s) de revision del %s al %s (capacidad %d%s)',
                    count($nuevos),
                    $desde->format('d/m/Y'),
                    $hasta->format('d/m/Y'),
                    $capacidad,
                    $soloLaborables ? ', solo L-V' : '',
                ),
                $actorUserId,
            );
        }

        return count($nuevos);
    }
}
