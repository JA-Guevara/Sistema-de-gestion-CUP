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

final readonly class CreateGrupo
{
    public function __construct(private GrupoRepository $grupos, private GestionRepository $gestiones, private RecordLogEntry $audit)
    {
    }

    public function execute(GrupoInput $input): Grupo
    {
        $gestion = $this->loadGestion($input);
        $existing = $this->loadExistingGrupo($gestion, $input);
        $this->validateGrupoData($input);
        $this->validateBusinessRules($existing);
        $grupo = $this->createGrupo($gestion, $input);
        $this->saveGrupo($grupo);
        $this->registerAudit($grupo, $input);
        $this->notifyGrupoCreated($grupo);

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

    private function validateBusinessRules(?Grupo $existing): void
    {
        if ($existing !== null) {
            throw new GrupoException('Ya existe un grupo con ese codigo en la gestion.');
        }
    }

    private function createGrupo(Gestion $gestion, GrupoInput $input): Grupo
    {
        $grupo = new Grupo();
        $grupo->configure($gestion, $input->codigo, $input->nombre, $input->cupo, $input->inscritosEstimados);

        return $grupo;
    }

    private function saveGrupo(Grupo $grupo): void
    {
        $this->grupos->save($grupo);
    }

    private function registerAudit(Grupo $grupo, GrupoInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::GRUPOS, sprintf('Se creo el grupo %s para la gestion %s.', $grupo->codigo, $grupo->gestion->codigo), $input->actorUserId);
    }

    private function notifyGrupoCreated(Grupo $grupo): void
    {
        // Punto de extension.
    }
}
