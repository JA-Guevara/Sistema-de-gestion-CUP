<?php

declare(strict_types=1);

namespace App\Inscripcion\Infrastructure\Payment;

use App\Inscripcion\Domain\Entity\Pago;
use App\Inscripcion\Domain\Exception\InscripcionException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Adaptador de la pasarela Stripe (Checkout alojado).
 *
 * Encapsula el SDK oficial. Las claves vienen por variables de entorno
 * (STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET): en dev/test usar las *_test_,
 * en produccion las *_live_. El cliente se crea de forma perezosa para que la
 * app arranque aunque las claves no esten configuradas todavia.
 */
final class StripeGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly string $stripeWebhookSecret,
    ) {
    }

    /** Hay clave secreta configurada (se puede cobrar). */
    public function isConfigured(): bool
    {
        return trim($this->stripeSecretKey) !== '';
    }

    /** Hay secreto de webhook configurado (se pueden verificar callbacks). */
    public function hasWebhookSecret(): bool
    {
        return trim($this->stripeWebhookSecret) !== '';
    }

    /**
     * Crea una sesion de Checkout para un pago y devuelve la URL de pago de Stripe.
     */
    public function createCheckoutSession(Pago $pago, string $successUrl, string $cancelUrl, string $descripcion): Session
    {
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $pago->email,
            'client_reference_id' => (string) $pago->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($pago->currency),
                    'unit_amount' => $pago->amount,
                    'product_data' => ['name' => $descripcion],
                ],
            ]],
            'metadata' => [
                'pago_id' => (string) $pago->id,
                'inscripcion_id' => (string) ($pago->inscripcion->id ?? ''),
            ],
        ]);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Verifica la firma del webhook y devuelve el evento. Lanza si la firma no
     * es valida o no hay secreto configurado.
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        if (!$this->hasWebhookSecret()) {
            throw new InscripcionException('Webhook de Stripe no configurado.');
        }

        return Webhook::constructEvent($payload, $signatureHeader, $this->stripeWebhookSecret);
    }

    private function client(): StripeClient
    {
        if (!$this->isConfigured()) {
            throw new InscripcionException('La pasarela de pago no esta configurada. Define STRIPE_SECRET_KEY.');
        }

        return $this->client ??= new StripeClient($this->stripeSecretKey);
    }
}
