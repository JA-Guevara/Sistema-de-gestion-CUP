<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Entity;

use App\Inscripcion\Domain\Catalog\EstadoPago;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pago del arancel de inscripcion via pasarela (Stripe Checkout).
 *
 * - Una postulacion puede tener varios intentos de pago (PENDIENTE/CANCELADO),
 *   pero a lo sumo uno PAGADO.
 * - El monto se guarda en la unidad minima de la moneda (centavos) como en Stripe.
 * - Conserva las referencias de Stripe (session + payment intent) para conciliar.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pagos')]
#[ORM\Index(name: 'idx_pagos_inscripcion', columns: ['inscripcion_id'])]
#[ORM\Index(name: 'idx_pagos_estado', columns: ['estado'])]
#[ORM\Index(name: 'idx_pagos_stripe_session', columns: ['stripe_session_id'])]
class Pago
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Inscripcion::class)]
    #[ORM\JoinColumn(name: 'inscripcion_id', nullable: false, onDelete: 'CASCADE')]
    public Inscripcion $inscripcion;

    /** Id del usuario que paga (el propio postulante). */
    #[ORM\Column]
    public int $userId;

    /** Correo del pagador, cacheado al momento del pago. */
    #[ORM\Column(length: 180)]
    public string $email;

    /** Monto en la unidad minima de la moneda (centavos). */
    #[ORM\Column]
    public int $amount;

    /** Codigo ISO de moneda en mayusculas (ej. BOB). */
    #[ORM\Column(length: 3)]
    public string $currency;

    #[ORM\Column(length: 16)]
    public string $estado = EstadoPago::PENDIENTE;

    /** Proveedor de la pasarela. Hoy: 'stripe'. */
    #[ORM\Column(length: 20)]
    public string $provider = 'stripe';

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $stripeSessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $stripePaymentIntentId = null;

    /** Observacion del admin (cuando el estado es OBSERVADO o al editar). */
    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $observacion = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
    }

    public function isPagado(): bool
    {
        return $this->estado === EstadoPago::PAGADO;
    }

    public function isPendiente(): bool
    {
        return $this->estado === EstadoPago::PENDIENTE;
    }

    public function marcarPagado(?string $paymentIntentId): void
    {
        $this->estado = EstadoPago::PAGADO;
        $this->stripePaymentIntentId = $paymentIntentId ?? $this->stripePaymentIntentId;
        $this->paidAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
    }

    public function marcarCancelado(): void
    {
        if ($this->isPagado()) {
            return;
        }

        $this->estado = EstadoPago::CANCELADO;
    }

    public function marcarFallido(): void
    {
        if ($this->isPagado()) {
            return;
        }

        $this->estado = EstadoPago::FALLIDO;
    }

    public function marcarExpirado(): void
    {
        if ($this->isPagado()) {
            return;
        }

        $this->estado = EstadoPago::EXPIRADO;
    }

    public function marcarEstado(string $estado): void
    {
        $this->estado = $estado;
    }

    /** Monto en unidades mayores de la moneda (ej. 100.00) para mostrar. */
    public function montoDecimal(): float
    {
        return $this->amount / 100;
    }
}
