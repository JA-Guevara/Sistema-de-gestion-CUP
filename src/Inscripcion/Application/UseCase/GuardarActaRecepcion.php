<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Application\DTO\ActaRecepcionInput;
use App\Inscripcion\Domain\Catalog\EstadoVerificacion;
use App\Inscripcion\Domain\Catalog\RequisitoCatalog;
use App\Inscripcion\Domain\Entity\VerificacionDocumento;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Inscripcion\Infrastructure\Persistence\VerificacionDocumentoRepository;

/**
 * Guarda el acta de control de recepcion presencial: por cada requisito del
 * tipo (estudiante/docente) registra su estado (Entregado/Observado/No
 * presento) y la observacion. Hace upsert de cada linea del acta.
 */
final readonly class GuardarActaRecepcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private VerificacionDocumentoRepository $verificaciones,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(ActaRecepcionInput $input): void
    {
        $inscripcion = $this->inscripciones->findById($input->inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->esTerminal()) {
            throw new InscripcionException('No se puede registrar el acta de una postulacion rechazada o anulada.');
        }

        $existentes = [];
        foreach ($inscripcion->verificaciones as $verificacion) {
            $existentes[$verificacion->requisitoCodigo] = $verificacion;
        }

        $aGuardar = [];
        foreach (RequisitoCatalog::paraTipo($inscripcion->tipo) as $requisito) {
            $codigo = $requisito['codigo'];
            $estado = strtoupper(trim($input->estados[$codigo] ?? EstadoVerificacion::PENDIENTE));
            if (!EstadoVerificacion::isValid($estado)) {
                $estado = EstadoVerificacion::PENDIENTE;
            }

            $observacion = $input->observaciones[$codigo] ?? null;
            if ($estado === EstadoVerificacion::OBSERVADO && ($observacion === null || trim($observacion) === '')) {
                throw new InscripcionException(sprintf('Indica la observacion del requisito "%s".', $requisito['etiqueta']));
            }

            $verificacion = $existentes[$codigo] ?? null;
            if ($verificacion === null) {
                $verificacion = new VerificacionDocumento($inscripcion, $codigo);
                // Mantener la coleccion en memoria al dia para que resumenActa()
                // (usado en el evento de bitacora) cuente las lineas recien creadas.
                $inscripcion->verificaciones->add($verificacion);
            }
            $verificacion->registrar($estado, $observacion, $input->actorUserId);
            $aGuardar[] = $verificacion;
        }

        $this->verificaciones->saveMany($aGuardar);

        $this->events->actaRegistrada($inscripcion->ci, $inscripcion->resumenActa(), $input->actorUserId);
    }
}
