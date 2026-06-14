<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\PagoEvents;
use App\Inscripcion\Domain\Catalog\EstadoPago;
use App\Inscripcion\Domain\Entity\Pago;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\PagoRepository;

/**
 * Actualiza estados no exitosos de un pago Stripe.
 *
 * Se usa para cancelaciones del usuario, sesiones expiradas y pagos fallidos.
 * Nunca pisa un pago ya PAGADO.
 */
final readonly class ActualizarEstadoPagoStripe
{
    private const ESTADOS_PERMITIDOS = [
        EstadoPago::CANCELADO,
        EstadoPago::FALLIDO,
        EstadoPago::EXPIRADO,
    ];

    public function __construct(
        private PagoRepository $pagos,
        private PagoEvents $events,
    ) {
    }

    public function execute(string $sessionId, string $estado, ?int $actorUserId = null): ?Pago
    {
        $pago = $this->loadPago($sessionId);
        if ($pago === null) {
            return null;
        }

        $this->validateEstado($estado);
        $this->updatePago($pago, $estado);
        $this->savePago($pago);
        $this->registerAudit($pago, $actorUserId);

        return $pago;
    }

    private function loadPago(string $sessionId): ?Pago
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        return $this->pagos->findByStripeSessionId($sessionId);
    }

    private function validateEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS_PERMITIDOS, true)) {
            throw new InscripcionException('Estado de pago no permitido para esta operacion.');
        }
    }

    private function updatePago(Pago $pago, string $estado): void
    {
        if ($pago->isPagado()) {
            return;
        }

        match ($estado) {
            EstadoPago::CANCELADO => $pago->marcarCancelado(),
            EstadoPago::FALLIDO => $pago->marcarFallido(),
            EstadoPago::EXPIRADO => $pago->marcarExpirado(),
            default => null,
        };
    }

    private function savePago(Pago $pago): void
    {
        $this->pagos->save($pago);
    }

    private function registerAudit(Pago $pago, ?int $actorUserId): void
    {
        if ($pago->isPagado()) {
            return;
        }

        $this->events->pagoEstadoActualizado($pago->inscripcion->ci, $pago->estado, $actorUserId ?? $pago->userId);
    }
}
