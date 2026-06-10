<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\UseCase;

use App\Academico\Grupo\Application\DTO\GenerateGruposInput;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Domain\Exception\GrupoException;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class GenerateGrupos
{
    public function __construct(private GrupoRepository $grupos, private GestionRepository $gestiones, private RecordLogEntry $audit)
    {
    }

    public function execute(GenerateGruposInput $input): array
    {
        $gestion = $this->loadGestion($input);
        $this->validateConfiguration($gestion);
        $this->validateBusinessRules($input);
        $generatedGroups = $this->createGroups($gestion, $input);
        $this->saveGroups($generatedGroups);
        $this->registerAudit($generatedGroups, $input);
        $this->notifyGroupsGenerated($generatedGroups);

        return $generatedGroups;
    }

    private function loadGestion(GenerateGruposInput $input): Gestion
    {
        $gestion = $this->gestiones->findById($input->gestionId);
        if ($gestion === null) {
            throw new GrupoException('La gestion seleccionada no existe.');
        }

        return $gestion;
    }

    private function validateConfiguration(Gestion $gestion): void
    {
        if ($gestion->configuracion === null || $gestion->configuracion->maxEstudiantesPorGrupo <= 0) {
            throw new GrupoException('La gestion no tiene configurado el maximo de estudiantes por grupo.');
        }
    }

    private function validateBusinessRules(GenerateGruposInput $input): void
    {
        if ($input->totalInscritos <= 0) {
            throw new GrupoException('El total de inscritos debe ser mayor a cero.');
        }
    }

    /** @return list<Grupo> */
    private function createGroups(Gestion $gestion, GenerateGruposInput $input): array
    {
        $max = $gestion->configuracion->maxEstudiantesPorGrupo;
        $quantity = (int) ceil($input->totalInscritos / $max);
        $groups = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $groups[] = $this->createGroup($gestion, $i, $max, $input->totalInscritos);
        }

        return $groups;
    }

    private function createGroup(Gestion $gestion, int $index, int $max, int $total): Grupo
    {
        $grupo = new Grupo();
        $assigned = min($max, max(0, $total - (($index - 1) * $max)));
        $grupo->configure($gestion, sprintf('G-%02d', $index), sprintf('Grupo %02d', $index), $max, $assigned);

        return $grupo;
    }

    private function saveGroups(array $groups): void
    {
        $this->grupos->saveMany($groups);
    }

    private function registerAudit(array $groups, GenerateGruposInput $input): void
    {
        $gestionCode = $groups !== [] ? $groups[0]->gestion->codigo : 'sin gestion';
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::GRUPOS, sprintf('Se generaron %d grupos para %s con %d inscritos.', count($groups), $gestionCode, $input->totalInscritos), $input->actorUserId);
    }

    private function notifyGroupsGenerated(array $groups): void
    {
        // Punto de extension.
    }
}
