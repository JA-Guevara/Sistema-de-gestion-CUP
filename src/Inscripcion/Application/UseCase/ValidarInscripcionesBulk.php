<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Application\DTO\InscripcionesBulkInput;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Aprobacion masiva: valida (PRESENTADA -> VALIDADA) las postulaciones
 * seleccionadas que tengan TODA su documentacion aprobada. Las que no cumplen
 * el gate (borrador, ya resueltas o con documentos pendientes) se omiten y se
 * reportan aparte; no abortan el lote completo.
 */
final readonly class ValidarInscripcionesBulk
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private InscripcionEvents $events,
    ) {
    }

    /**
     * @return array{validadas:int, omitidas:int}
     */
    public function execute(InscripcionesBulkInput $input): array
    {
        $seleccionadas = $this->inscripciones->findByIds($input->ids);
        if ($seleccionadas === []) {
            throw new InscripcionException('Selecciona al menos una postulacion.');
        }

        /** @var list<Inscripcion> $validadas */
        $validadas = [];
        $omitidas = 0;
        foreach ($seleccionadas as $inscripcion) {
            if (!$inscripcion->isPresentada() || !$inscripcion->todosAprobados()) {
                $omitidas++;
                continue;
            }

            $inscripcion->validar($input->actorUserId);
            $validadas[] = $inscripcion;
        }

        if ($validadas !== []) {
            $this->inscripciones->saveMany($validadas);
            foreach ($validadas as $inscripcion) {
                $this->events->validada($inscripcion->ci, $input->actorUserId);
            }
        }

        return ['validadas' => count($validadas), 'omitidas' => $omitidas];
    }
}
