<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class InscripcionEvents
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function creada(string $ci, string $nombre, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::INSCRIPCIONES, sprintf('Inscripcion CI %s', $ci), sprintf('Creo inscripcion de %s (CI: %s).', $nombre, $ci), $actorUserId, $userLabel);
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     */
    public function editada(string $ci, array $antes, array $despues, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->updated(ModuleCatalog::INSCRIPCIONES, sprintf('Inscripcion CI %s', $ci), sprintf('Edito inscripcion CI %s.', $ci), $antes, $despues, $actorUserId, $userLabel);
    }

    public function eliminada(string $ci, string $nombre, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->deleted(ModuleCatalog::INSCRIPCIONES, sprintf('Inscripcion CI %s', $ci), sprintf('Elimino inscripcion de %s (CI: %s).', $nombre, $ci), $actorUserId, $userLabel);
    }

    public function documentoSubido(string $ci, string $nombreDocumento, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->created(ModuleCatalog::INSCRIPCIONES, sprintf('Inscripcion CI %s', $ci), sprintf('Subio documento "%s" a inscripcion CI %s.', $nombreDocumento, $ci), $actorUserId, $userLabel);
    }

    public function documentoEliminado(string $ci, string $nombreDocumento, ?int $actorUserId, ?string $userLabel = null): void
    {
        $this->audit->deleted(ModuleCatalog::INSCRIPCIONES, sprintf('Inscripcion CI %s', $ci), sprintf('Elimino documento "%s" de inscripcion CI %s.', $nombreDocumento, $ci), $actorUserId, $userLabel);
    }
}
