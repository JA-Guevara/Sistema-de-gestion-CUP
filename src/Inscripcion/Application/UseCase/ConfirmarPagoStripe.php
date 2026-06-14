<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\PagoEvents;
use App\Inscripcion\Domain\Entity\Pago;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Payment\StripeGateway;
use App\Inscripcion\Infrastructure\Persistence\PagoRepository;

/**
 * Confirma el pago de una sesion de Stripe Checkout y, en consecuencia, confirma
 * la inscripcion (asignando el rol). Es IDEMPOTENTE: lo invocan tanto el webhook
 * (autoritativo) como la pagina de exito, y puede ejecutarse varias veces sin
 * doble efecto.
 */
final readonly class ConfirmarPagoStripe
{
    public function __construct(
        private PagoRepository $pagos,
        private StripeGateway $stripe,
        private ConfirmarInscripcion $confirmar,
        private PagoEvents $events,
    ) {
    }

    /**
     * Procesa el pago asociado a una sesion de Checkout. Devuelve el Pago si se
     * encontro (ya pagado o recien confirmado), o null si la sesion es desconocida.
     */
    public function porSession(string $sessionId): ?Pago
    {
        $pago = $this->pagos->findByStripeSessionId($sessionId);
        if ($pago === null) {
            return null;
        }

        if ($pago->isPagado()) {
            return $pago; // Ya procesado: idempotente.
        }

        $session = $this->stripe->retrieveSession($sessionId);
        if (($session->payment_status ?? null) !== 'paid') {
            return $pago; // Aun no se completo el pago.
        }

        // Primero confirmamos la inscripcion; si falla, no marcamos el pago como procesado.
        $this->confirmarInscripcion($pago);

        $paymentIntent = is_string($session->payment_intent ?? null) ? $session->payment_intent : null;
        $pago->marcarPagado($paymentIntent);
        $this->pagos->save($pago);

        $this->events->pagoConfirmado($pago->inscripcion->ci, $pago->montoDecimal(), $pago->currency, $pago->userId);

        return $pago;
    }

    private function confirmarInscripcion(Pago $pago): void
    {
        $inscripcion = $pago->inscripcion;
        if ($inscripcion->isConfirmada()) {
            return;
        }

        try {
            $this->confirmar->execute((int) $inscripcion->id, $pago->userId);
        } catch (InscripcionException $exception) {
            if ($inscripcion->isConfirmada()) {
                return;
            }

            throw $exception;
        }

        if (!$inscripcion->isConfirmada()) {
            throw new InscripcionException('El pago fue recibido, pero la inscripcion no pudo confirmarse automaticamente.');
        }
    }
}
