<?php

declare(strict_types=1);

namespace App\Inscripcion\UI\Controller;

use App\Inscripcion\Application\UseCase\ActualizarEstadoPagoStripe;
use App\Inscripcion\Application\UseCase\ConfirmarPagoStripe;
use App\Inscripcion\Domain\Catalog\EstadoPago;
use App\Inscripcion\Infrastructure\Payment\StripeGateway;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint del webhook de Stripe. Ruta top-level (/stripe/webhook), SIN sesion
 * ni CSRF: la autenticidad se verifica con la firma del webhook. Es la via
 * autoritativa de confirmacion del pago (la pagina de exito es solo respaldo UX).
 *
 * El nombre de ruta 'stripe_webhook' no coincide con ningun prefijo de
 * PermissionGuard, por lo que queda accesible sin permisos; y SessionGuard no
 * actua sobre peticiones sin sesion (Stripe).
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly ConfirmarPagoStripe $confirmarPago,
        private readonly ActualizarEstadoPagoStripe $actualizarEstadoPago,
    ) {
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook_status', methods: ['GET'])]
    public function status(): Response
    {
        return new Response(
            "Webhook de Stripe activo.\nEste endpoint se prueba abriendolo en GET, pero Stripe debe enviar eventos por POST firmado.",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = (string) $request->headers->get('Stripe-Signature', '');

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (\Throwable) {
            // Firma invalida o webhook no configurado.
            return new Response('Firma invalida', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->processEvent($event->type, $event->data->object ?? null);
        } catch (\Throwable) {
            // Devolvemos 500 para que Stripe reintente el envio.
            return new Response('Error procesando el pago', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('', Response::HTTP_OK);
    }

    private function processEvent(string $eventType, mixed $object): void
    {
        $sessionId = $this->sessionIdFromObject($object);
        if ($sessionId === null) {
            return;
        }

        if ($eventType === 'checkout.session.completed' || $eventType === 'checkout.session.async_payment_succeeded') {
            $this->confirmarPago->porSession($sessionId);

            return;
        }

        if ($eventType === 'checkout.session.expired') {
            $this->actualizarEstadoPago->execute($sessionId, EstadoPago::EXPIRADO);

            return;
        }

        if ($eventType === 'checkout.session.async_payment_failed') {
            $this->actualizarEstadoPago->execute($sessionId, EstadoPago::FALLIDO);
        }
    }

    private function sessionIdFromObject(mixed $object): ?string
    {
        if (!is_object($object)) {
            return null;
        }

        $sessionId = $object->id ?? null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }
}
