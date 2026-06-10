<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/** EventLog del módulo Carreras. */
final readonly class CarreraEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function creada(string $codigo, string $nombre, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::CARRERAS, sprintf('Carrera %s', $codigo), sprintf('Creó la carrera %s - %s.', $codigo, $nombre), $actorUserId, $userLabel);
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function editada(string $codigo, array $antes, array $despues, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->updated(ModuleCatalog::CARRERAS, sprintf('Carrera %s', $codigo), sprintf('Editó la carrera %s.', $codigo), $antes, $despues, $actorUserId, $userLabel);
    }

    public function activada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->activated(ModuleCatalog::CARRERAS, sprintf('Carrera %s', $codigo), sprintf('Activó la carrera %s.', $codigo), $actorUserId, $userLabel);
    }

    public function desactivada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->deactivated(ModuleCatalog::CARRERAS, sprintf('Carrera %s', $codigo), sprintf('Desactivó la carrera %s.', $codigo), $actorUserId, $userLabel);
    }
}
