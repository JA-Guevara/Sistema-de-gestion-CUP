<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/** EventLog del módulo Gestiones CUP. */
final readonly class GestionEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function creada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::GESTIONES_CUP, sprintf('Gestión %s', $codigo), sprintf('Creó la gestión CUP %s.', $codigo), $actorUserId, $userLabel);
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function editada(string $codigo, array $antes, array $despues, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->updated(ModuleCatalog::GESTIONES_CUP, sprintf('Gestión %s', $codigo), sprintf('Editó la gestión CUP %s.', $codigo), $antes, $despues, $actorUserId, $userLabel);
    }

    public function activada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->activated(ModuleCatalog::GESTIONES_CUP, sprintf('Gestión %s', $codigo), sprintf('Activó la gestión CUP %s.', $codigo), $actorUserId, $userLabel);
    }

    public function inscripcionAbierta(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->stateChanged(ModuleCatalog::GESTIONES_CUP, sprintf('Gestión %s', $codigo), sprintf('Abrió la inscripción de la gestión %s.', $codigo), 'CERRADA', 'ABIERTA', $actorUserId, $userLabel);
    }

    public function inscripcionCerrada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->stateChanged(ModuleCatalog::GESTIONES_CUP, sprintf('Gestión %s', $codigo), sprintf('Cerró la inscripción de la gestión %s.', $codigo), 'ABIERTA', 'CERRADA', $actorUserId, $userLabel);
    }
}
