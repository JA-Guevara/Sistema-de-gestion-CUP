<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\UseCase;

use App\Academico\Grupo\Application\DTO\GrupoInput;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Domain\Exception\GrupoException;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class UpdateGrupo
{
    public function __construct(private GrupoRepository $grupos, private GestionRepository $gestiones, private RecordLogEntry $audit)
    {
    }

    public function execute(int $grupoId, GrupoInput $input): Grupo
    {
        $grupo = $this->loadGrupo($grupoId);
        $gestion = $this->loadGestion($input);
        $existing = $this->loadExistingGrupo($gestion, $input);
        $this->validateGrupoData($input);
        $this->validateBusinessRules($grupo, $existing);
        $this->updateGrupo($grupo, $gestion, $input);
        $this->saveGrupo($grupo);
        $this->registerAudit($grupo, $input);
        $this->notifyGrupoUpdated($grupo);

        return $grupo;
    }

    private function loadGrupo(int $grupoId): Grupo
    {
        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null) {
            throw new GrupoException('El grupo solicitado no existe.');
        }

        return $grupo;
    }

    private function loadGestion(GrupoInput $input): Gestion
    {
        $gestion = $this->gestiones->findById($input->gestionId);
        if ($gestion === null) {
            throw new GrupoException('La gestion seleccionada no existe.');
        }

        return $gestion;
    }

    private function loadExistingGrupo(Gestion $gestion, GrupoInput $input): ?Grupo
    {
        return $this->grupos->findByGestionAndCodigo($gestion, $input->codigo);
    }

    private function validateGrupoData(GrupoInput $input): void
    {
        if (trim($input->codigo) === '' || trim($input->nombre) === '' || $input->cupo <= 0) {
            throw new GrupoException('Codigo, nombre y cupo de grupo son obligatorios.');
        }
    }

    private function validateBusinessRules(Grupo $grupo, ?Grupo $existing): void
    {
        if ($existing !== null && $existing->id !== $grupo->id) {
            throw new GrupoException('Ya existe un grupo con ese codigo en la gestion.');
        }
    }

    private function updateGrupo(Grupo $grupo, Gestion $gestion, GrupoInput $input): void
    {
        $grupo->configure($gestion, $input->codigo, $input->nombre, $input->cupo, $input->inscritosEstimados);
    }

    private function saveGrupo(Grupo $grupo): void
    {
        $this->grupos->save($grupo);
    }

    private function registerAudit(Grupo $grupo, GrupoInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::GRUPOS, sprintf('Se edito el grupo %s de la gestion %s.', $grupo->codigo, $grupo->gestion->codigo), $input->actorUserId);
    }

    private function notifyGrupoUpdated(Grupo $grupo): void
    {
        // Punto de extension.
    }
}
