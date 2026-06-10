<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/** EventLog del módulo Aulas. */
final readonly class AulaEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function creada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::AULAS, sprintf('Aula %s', $codigo), sprintf('Creó el aula %s.', $codigo), $actorUserId, $userLabel);
    }

    public function creadasMasivas(int $cantidad, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->log(ModuleCatalog::AULAS, \App\Bitacora\Domain\Catalog\ActionCatalog::IMPORT, sprintf('Cargó masivamente %d aulas.', $cantidad), entity: 'Aulas', userId: $actorUserId, userLabel: $userLabel, extra: ['cantidad' => $cantidad]);
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function editada(string $codigo, array $antes, array $despues, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->updated(ModuleCatalog::AULAS, sprintf('Aula %s', $codigo), sprintf('Editó el aula %s.', $codigo), $antes, $despues, $actorUserId, $userLabel);
    }

    public function activada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->activated(ModuleCatalog::AULAS, sprintf('Aula %s', $codigo), sprintf('Activó el aula %s.', $codigo), $actorUserId, $userLabel);
    }

    public function desactivada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->deactivated(ModuleCatalog::AULAS, sprintf('Aula %s', $codigo), sprintf('Desactivó el aula %s.', $codigo), $actorUserId, $userLabel);
    }
}
