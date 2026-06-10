<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/** EventLog del módulo Materias. */
final readonly class MateriaEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function creada(string $codigo, string $nombre, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::MATERIAS, sprintf('Materia %s', $codigo), sprintf('Creó la materia %s - %s.', $codigo, $nombre), $actorUserId, $userLabel);
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function editada(string $codigo, array $antes, array $despues, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->updated(ModuleCatalog::MATERIAS, sprintf('Materia %s', $codigo), sprintf('Editó la materia %s.', $codigo), $antes, $despues, $actorUserId, $userLabel);
    }

    public function activada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->activated(ModuleCatalog::MATERIAS, sprintf('Materia %s', $codigo), sprintf('Activó la materia %s.', $codigo), $actorUserId, $userLabel);
    }

    public function desactivada(string $codigo, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->deactivated(ModuleCatalog::MATERIAS, sprintf('Materia %s', $codigo), sprintf('Desactivó la materia %s.', $codigo), $actorUserId, $userLabel);
    }
}
