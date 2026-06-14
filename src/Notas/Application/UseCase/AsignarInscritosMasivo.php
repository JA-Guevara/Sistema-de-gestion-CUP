<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Application\DTO\AsignarGruposMasivoInput;
use App\Notas\Domain\Entity\AsignacionGrupo;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

final readonly class AsignarInscritosMasivo
{
    public function __construct(
        private AsignacionGrupoRepository $asignaciones,
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
        private NotasEvents $events,
    ) {
    }

    /** @return array{asignados:int, omitidos:int} */
    public function execute(AsignarGruposMasivoInput $input): array
    {
        $gestion = $this->loadGestion();
        $materia = $this->loadMateria($input);
        $grupos = $this->loadGrupos($input);
        $existentes = $this->loadExistingAssignments($materia, $gestion);
        $inscripciones = $this->loadInscripciones($gestion, $input);
        $this->validateBusinessRules($materia, $grupos, $gestion);
        $resultado = $this->createOrUpdateAssignments($inscripciones, $existentes, $materia, $grupos, $input);
        $this->saveAssignments($resultado['asignados']);
        $this->registerAudit($materia, $grupos, count($resultado['asignados']), $input);
        $this->notify($materia, $resultado);

        return ['asignados' => count($resultado['asignados']), 'omitidos' => $resultado['omitidos']];
    }

    private function loadGestion(): Gestion
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        return $gestion;
    }

    private function loadMateria(AsignarGruposMasivoInput $input): Materia
    {
        $materia = $this->materias->findById($input->materiaId);
        if ($materia === null) {
            throw new NotaException('La materia seleccionada no existe.');
        }

        return $materia;
    }

    /** @return list<Grupo> */
    private function loadGrupos(AsignarGruposMasivoInput $input): array
    {
        return $this->grupos->findByIds($input->grupoIds);
    }

    /** @return array<int, AsignacionGrupo> */
    private function loadExistingAssignments(Materia $materia, Gestion $gestion): array
    {
        $indexed = [];
        foreach ($this->asignaciones->listByMateriaAndGestion((int) $materia->id, (int) $gestion->id) as $asignacion) {
            $indexed[(int) $asignacion->inscripcion->id] = $asignacion;
        }

        return $indexed;
    }

    /** @return list<Inscripcion> */
    private function loadInscripciones(Gestion $gestion, AsignarGruposMasivoInput $input): array
    {
        return array_values(array_filter(
            $this->inscripciones->listByGestionTipoEstado((int) $gestion->id, TipoPostulacion::ESTUDIANTE, EstadoInscripcion::CONFIRMADA),
            fn (Inscripcion $inscripcion): bool => $this->matchesTurno($inscripcion, $input->turnoPreferencia),
        ));
    }

    /** @param list<Grupo> $grupos */
    private function validateBusinessRules(Materia $materia, array $grupos, Gestion $gestion): void
    {
        if (!$materia->isActive()) {
            throw new NotaException('La materia seleccionada no esta activa.');
        }

        if ($grupos === []) {
            throw new NotaException('Selecciona al menos un grupo.');
        }

        foreach ($grupos as $grupo) {
            $this->validateGrupo($grupo, $gestion);
        }
    }

    private function validateGrupo(Grupo $grupo, Gestion $gestion): void
    {
        if ($grupo->gestion->id !== $gestion->id) {
            throw new NotaException(sprintf('El grupo %s no pertenece a la gestion activa.', $grupo->codigo));
        }

        if (!$grupo->isOpen()) {
            throw new NotaException(sprintf('El grupo %s esta cerrado.', $grupo->codigo));
        }
    }

    /**
     * @param list<Inscripcion> $inscripciones
     * @param array<int, AsignacionGrupo> $existentes
     * @param list<Grupo> $grupos
     * @return array{asignados:list<AsignacionGrupo>, omitidos:int}
     */
    private function createOrUpdateAssignments(array $inscripciones, array $existentes, Materia $materia, array $grupos, AsignarGruposMasivoInput $input): array
    {
        $capacidades = $this->buildCapacityMap($grupos, $materia);
        $asignados = [];
        $omitidos = 0;

        foreach ($inscripciones as $inscripcion) {
            $existente = $existentes[(int) $inscripcion->id] ?? null;
            if ($existente !== null && !$input->reasignarExistentes) {
                $omitidos++;
                continue;
            }

            $this->releaseCurrentSlotIfNeeded($existente, $capacidades, $input);
            $target = $this->nextAvailableGroup($grupos, $capacidades);
            if ($target === null) {
                $omitidos++;
                continue;
            }

            $asignacion = $this->buildAssignment($inscripcion, $existente, $materia, $target);
            if ($asignacion === null) {
                $omitidos++;
                continue;
            }

            $capacidades[(int) $target->id]++;
            $asignados[] = $asignacion;
        }

        return ['asignados' => $asignados, 'omitidos' => $omitidos];
    }

    /** @param array<int, int> $capacidades */
    private function releaseCurrentSlotIfNeeded(?AsignacionGrupo $existente, array &$capacidades, AsignarGruposMasivoInput $input): void
    {
        if ($existente === null || !$input->reasignarExistentes) {
            return;
        }

        $grupoId = (int) $existente->grupo->id;
        if (!array_key_exists($grupoId, $capacidades)) {
            return;
        }

        $capacidades[$grupoId] = max(0, $capacidades[$grupoId] - 1);
    }

    /** @param list<Grupo> $grupos @return array<int, int> */
    private function buildCapacityMap(array $grupos, Materia $materia): array
    {
        $map = [];
        foreach ($grupos as $grupo) {
            $map[(int) $grupo->id] = count($this->asignaciones->listByMateriaAndGrupo((int) $materia->id, (int) $grupo->id));
        }

        return $map;
    }

    /** @param list<Grupo> $grupos @param array<int, int> $capacidades */
    private function nextAvailableGroup(array $grupos, array $capacidades): ?Grupo
    {
        usort($grupos, static fn (Grupo $a, Grupo $b): int => ($capacidades[(int) $a->id] ?? 0) <=> ($capacidades[(int) $b->id] ?? 0));

        foreach ($grupos as $grupo) {
            if (($capacidades[(int) $grupo->id] ?? 0) < $grupo->cupo) {
                return $grupo;
            }
        }

        return null;
    }

    private function buildAssignment(Inscripcion $inscripcion, ?AsignacionGrupo $existente, Materia $materia, Grupo $grupo): ?AsignacionGrupo
    {
        if ($existente !== null) {
            $existente->grupo = $grupo;
            return $existente;
        }

        $asignacion = new AsignacionGrupo();
        $asignacion->assign($inscripcion, $materia, $grupo);
        $this->asignaciones->persist($asignacion);

        return $asignacion;
    }

    /** @param list<AsignacionGrupo> $asignados */
    private function saveAssignments(array $asignados): void
    {
        if ($asignados !== []) {
            $this->asignaciones->flush();
        }
    }

    /** @param list<Grupo> $grupos */
    private function registerAudit(Materia $materia, array $grupos, int $cantidad, AsignarGruposMasivoInput $input): void
    {
        if ($cantidad === 0) {
            return;
        }

        $grupoLabel = implode(', ', array_map(static fn (Grupo $grupo): string => $grupo->codigo, $grupos));
        $this->events->estudiantesAsignados($this->materiaLabel($materia), $grupoLabel, $cantidad, $input->actorUserId);
    }

    /** @param array{asignados:list<AsignacionGrupo>, omitidos:int} $resultado */
    private function notify(Materia $materia, array $resultado): void
    {
        // No hay notificacion externa; el resultado se informa por flash en UI.
    }

    private function matchesTurno(Inscripcion $inscripcion, ?string $turno): bool
    {
        if ($turno === null || trim($turno) === '') {
            return true;
        }

        return mb_strtolower((string) $inscripcion->turnoPreferencia) === mb_strtolower(trim($turno));
    }

    private function materiaLabel(Materia $materia): string
    {
        return sprintf('%s - %s', $materia->codigo, $materia->nombre);
    }
}
