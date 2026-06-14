<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/**
 * Eventos de auditoria de los pagos de inscripcion (pasarela Stripe).
 * El actor (userId del postulante) se resuelve a su correo en la bitacora.
 */
final readonly class PagoEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function pagoIniciado(string $ci, float $monto, string $moneda, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::PAGOS,
            ActionCatalog::PAYMENT,
            sprintf('Inicio el pago del arancel de la inscripcion CI %s por %s %s.', $ci, number_format($monto, 2), $moneda),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }

    public function pagoConfirmado(string $ci, float $monto, string $moneda, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::PAGOS,
            ActionCatalog::PAYMENT,
            sprintf('Pago confirmado del arancel de la inscripcion CI %s por %s %s.', $ci, number_format($monto, 2), $moneda),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }

    public function pagoEstadoActualizado(string $ci, string $estado, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::PAGOS,
            ActionCatalog::PAYMENT,
            sprintf('Pago de la inscripcion CI %s actualizado a estado %s.', $ci, $estado),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }
}
