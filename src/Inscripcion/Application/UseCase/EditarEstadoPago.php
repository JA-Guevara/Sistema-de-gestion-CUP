<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\PagoEvents;
use App\Inscripcion\Domain\Catalog\EstadoPago;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\PagoRepository;

/**
 * Edicion manual del estado de un pago por el administrador:
 * PENDIENTE / PAGADO / OBSERVADO / ANULADO.
 *
 * - PAGADO confirma la inscripcion (asigna el rol), como el registro manual.
 * - OBSERVADO exige una observacion.
 * - ANULADO marca el pago como anulado (deja de contar como recaudado). NO
 *   revierte la inscripcion: si hay que retirar el rol, se anula la inscripcion
 *   desde Admisiones.
 */
final readonly class EditarEstadoPago
{
    public function __construct(
        private PagoRepository $pagos,
        private ConfirmarInscripcion $confirmar,
        private PagoEvents $events,
    ) {
    }

    public function execute(int $pagoId, string $estado, ?string $observacion, ?int $actorUserId): void
    {
        $estado = mb_strtoupper(trim($estado));
        if (!EstadoPago::isEditableAdmin($estado)) {
            throw new InscripcionException('Estado de pago no valido. Solo: pendiente, pagado, observado o anulado.');
        }

        $pago = $this->pagos->findById($pagoId);
        if ($pago === null) {
            throw new InscripcionException('El pago no existe.');
        }

        $observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;
        $inscripcion = $pago->inscripcion;

        if ($estado === EstadoPago::PAGADO) {
            if (!$inscripcion->esEstudiante()) {
                throw new InscripcionException('El pago del arancel solo aplica a estudiantes.');
            }
            if (!$inscripcion->isConfirmada()) {
                $this->confirmar->execute((int) $inscripcion->id, $actorUserId);
            }
            $pago->marcarPagado(null);
            $pago->observacion = $observacion;
        } elseif ($estado === EstadoPago::OBSERVADO) {
            if ($observacion === null) {
                throw new InscripcionException('Indica la observacion del pago.');
            }
            $pago->marcarEstado(EstadoPago::OBSERVADO);
            $pago->observacion = $observacion;
        } elseif ($estado === EstadoPago::ANULADO) {
            $pago->marcarEstado(EstadoPago::ANULADO);
            $pago->observacion = $observacion;
        } else { // PENDIENTE
            $pago->marcarEstado(EstadoPago::PENDIENTE);
            $pago->observacion = $observacion;
        }

        $this->pagos->save($pago);

        $this->events->pagoEstadoActualizado($inscripcion->ci, EstadoPago::label($estado), $actorUserId);
    }
}
