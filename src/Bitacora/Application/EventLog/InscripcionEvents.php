<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Bitacora\Application\Audit\AuditLogger;
use App\Bitacora\Domain\Catalog\ActionCatalog;
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

    public function revisionAgendada(string $ci, \DateTimeImmutable $fecha, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::INSCRIPCIONES,
            ActionCatalog::REPROGRAM,
            sprintf('Agendo revision de documentos de CI %s para %s.', $ci, $fecha->format('d/m/Y H:i')),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }

    public function validada(string $ci, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::INSCRIPCIONES,
            ActionCatalog::APPROVE,
            sprintf('Valido la documentacion de la postulacion CI %s.', $ci),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }

    public function rechazada(string $ci, ?string $motivo, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::INSCRIPCIONES,
            ActionCatalog::REJECT,
            sprintf('Rechazo la postulacion CI %s.%s', $ci, $motivo !== null && $motivo !== '' ? ' Motivo: ' . $motivo : ''),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }

    public function documentoRevisado(string $ci, string $nombreDocumento, string $estado, ?int $actorUserId): void
    {
        $this->audit->log(
            ModuleCatalog::INSCRIPCIONES,
            ActionCatalog::UPDATE,
            sprintf('Reviso el documento "%s" de CI %s: %s.', $nombreDocumento, $ci, $estado),
            entity: sprintf('Inscripcion CI %s', $ci),
            userId: $actorUserId,
        );
    }
}
