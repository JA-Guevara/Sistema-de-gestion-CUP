<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\UseCase;

use App\Academico\Grupo\Application\DTO\GrupoActionInput;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Domain\Exception\GrupoException;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class ToggleGrupoState
{
    public function __construct(private GrupoRepository $grupos, private RecordLogEntry $audit)
    {
    }

    public function execute(GrupoActionInput $input): Grupo
    {
        $grupo = $this->loadGrupo($input);
        $this->validateBusinessRules($grupo);
        $this->updateGrupoState($grupo);
        $this->saveGrupo($grupo);
        $this->registerAudit($grupo, $input);
        $this->notifyGrupoStateChanged($grupo);

        return $grupo;
    }

    private function loadGrupo(GrupoActionInput $input): Grupo
    {
        $grupo = $this->grupos->findById($input->grupoId);
        if ($grupo === null) {
            throw new GrupoException('El grupo solicitado no existe.');
        }

        return $grupo;
    }

    private function validateBusinessRules(Grupo $grupo): void
    {
        // Futuro: impedir cierre si hay inscripciones pendientes de mover.
    }

    private function updateGrupoState(Grupo $grupo): void
    {
        $grupo->isOpen() ? $grupo->close() : $grupo->open();
    }

    private function saveGrupo(Grupo $grupo): void
    {
        $this->grupos->save($grupo);
    }

    private function registerAudit(Grupo $grupo, GrupoActionInput $input): void
    {
        $action = $grupo->isOpen() ? ActionCatalog::ACTIVATE : ActionCatalog::DEACTIVATE;
        $this->audit->execute($action, ModuleCatalog::GRUPOS, sprintf('Se cambio el estado del grupo %s a %s.', $grupo->codigo, $grupo->estado), $input->actorUserId);
    }

    private function notifyGrupoStateChanged(Grupo $grupo): void
    {
        // Punto de extension.
    }
}
