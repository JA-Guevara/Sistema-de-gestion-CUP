<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\PagoEvents;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\PagoRepository;

/**
 * Registro de pago MANUAL por un administrador (sin pasarela). Marca el pago
 * como PAGADO y confirma la inscripcion (asigna el rol), igual que el flujo de
 * Stripe pero sin verificar con la pasarela. Es un respaldo administrativo:
 * "demostrar que se inscribio" cuando no se uso la pasarela. Queda auditado.
 */
final readonly class RegistrarPagoManual
{
    public function __construct(
        private PagoRepository $pagos,
        private ConfirmarInscripcion $confirmar,
        private PagoEvents $events,
    ) {
    }

    public function execute(int $pagoId, ?int $actorUserId): void
    {
        $pago = $this->pagos->findById($pagoId);
        if ($pago === null) {
            throw new InscripcionException('El pago no existe.');
        }
        if ($pago->isPagado()) {
            throw new InscripcionException('Este pago ya esta registrado como pagado.');
        }

        // Confirma la inscripcion (VALIDADA -> CONFIRMADA, asigna el rol). Si ya
        // estaba confirmada, no se vuelve a confirmar.
        $inscripcion = $pago->inscripcion;
        if (!$inscripcion->isConfirmada()) {
            $this->confirmar->execute((int) $inscripcion->id, $actorUserId);
        }

        $pago->marcarPagado(null);
        $this->pagos->save($pago);

        $this->events->pagoRegistradoManual($inscripcion->ci, $pago->montoDecimal(), $pago->currency, $actorUserId);
    }
}
