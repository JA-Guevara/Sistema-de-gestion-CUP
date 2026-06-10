<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/** EventLog del módulo Grupos. */
final readonly class GrupoEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function creado(string $codigo, string $gestion, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::GRUPOS, sprintf('Grupo %s', $codigo), sprintf('Creó el grupo %s en la gestión %s.', $codigo, $gestion), $actorUserId, $userLabel);
    }

    public function generados(int $cantidad, string $gestion, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->generated(ModuleCatalog::GRUPOS, sprintf('Gestión %s', $gestion), sprintf('Generó automáticamente %d grupos para la gestión %s.', $cantidad, $gestion), $actorUserId, $userLabel, ['cantidad' => $cantidad]);
    }

    public function cambioEstado(string $codigo, string $de, string $a, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->stateChanged(ModuleCatalog::GRUPOS, sprintf('Grupo %s', $codigo), sprintf('Cambió el estado del grupo %s de %s a %s.', $codigo, $de, $a), $de, $a, $actorUserId, $userLabel);
    }
}
