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
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

final readonly class GenerateGrupos
{
    public function __construct(
        private GrupoRepository $grupos,
        private GestionRepository $gestiones,
        private InscripcionRepository $inscripciones,
        private RecordLogEntry $audit,
    ) {
    }

    /** @return list<Grupo> */
    public function execute(GenerateGruposInput $input): array
    {
        $gestion = $this->loadGestion($input);
        $this->validateConfiguration($gestion);
        $total = $this->resolveTotalInscritos($input);
        $generatedGroups = $this->createGroups($gestion, $total);
        $this->saveGroups($generatedGroups);
        $this->registerAudit($generatedGroups, $total, $input->actorUserId);
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

    /**
     * Total de inscritos para el calculo: si el admin escribio un valor lo usa
     * (override manual); si no, cuenta automaticamente los estudiantes CONFIRMADOS
     * de la gestion.
     */
    private function resolveTotalInscritos(GenerateGruposInput $input): int
    {
        $total = $input->totalInscritos > 0
            ? $input->totalInscritos
            : $this->inscripciones->countByGestionTipoEstado($input->gestionId, TipoPostulacion::ESTUDIANTE, EstadoInscripcion::CONFIRMADA);

        if ($total <= 0) {
            throw new GrupoException('No hay estudiantes confirmados en esta gestion para generar grupos. Ingresa un total manualmente si deseas forzarlo.');
        }

        return $total;
    }

    /** @return list<Grupo> */
    private function createGroups(Gestion $gestion, int $totalInscritos): array
    {
        $max = $gestion->configuracion->maxEstudiantesPorGrupo;
        $quantity = (int) ceil($totalInscritos / $max);
        $groups = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $groups[] = $this->createGroup($gestion, $i, $max, $totalInscritos);
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

    /** @param list<Grupo> $groups */
    private function saveGroups(array $groups): void
    {
        $this->grupos->saveMany($groups);
    }

    /** @param list<Grupo> $groups */
    private function registerAudit(array $groups, int $totalInscritos, ?int $actorUserId): void
    {
        $gestionCode = $groups !== [] ? $groups[0]->gestion->codigo : 'sin gestion';
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::GRUPOS, sprintf('Se generaron %d grupos para %s con %d inscritos.', count($groups), $gestionCode, $totalInscritos), $actorUserId);
    }

    /** @param list<Grupo> $groups */
    private function notifyGroupsGenerated(array $groups): void
    {
        // Punto de extension.
    }
}
