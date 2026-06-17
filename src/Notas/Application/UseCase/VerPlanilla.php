<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Application\Security\PlanillaAccessPolicy;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Domain\Service\PromedioCalculator;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\NotaRepository;

final readonly class VerPlanilla
{
    private const DEFAULT_EXAMENES = 3;
    private const DEFAULT_NOTA_MINIMA = 60;

    public function __construct(
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private NotaRepository $notas,
        private PlanillaAccessPolicy $accessPolicy,
        private PromedioCalculator $calc,
    ) {
    }

    /**
     * @return array{
     *     materia: \App\Academico\Materia\Domain\Entity\Materia,
     *     grupo: \App\Academico\Grupo\Domain\Entity\Grupo,
     *     gestion: \App\Gestion\Domain\Entity\Gestion,
     *     cantidadExamenes: int,
     *     notaMinima: int,
     *     filas: list<array{inscripcion: \App\Inscripcion\Domain\Entity\Inscripcion, valores: array<int, int|null>, promedio: int|null, aprobado: bool|null}>
     * }
     */
    public function execute(int $materiaId, int $grupoId, ?int $actorUserId): array
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        $materia = $this->materias->findById($materiaId);
        if ($materia === null) {
            throw new NotaException('La materia seleccionada no existe.');
        }

        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null) {
            throw new NotaException('El grupo seleccionado no existe.');
        }

        // Autorizacion a nivel de objeto: el docente solo ve su materia+grupo.
        $this->accessPolicy->assertPuede($actorUserId, $materiaId, $grupoId, (int) $gestion->id);

        $cantidadExamenes = max(1, $gestion->configuracion?->cantidadExamenes ?? self::DEFAULT_EXAMENES);
        $notaMinima = $gestion->configuracion?->notaMinimaAprobacion ?? self::DEFAULT_NOTA_MINIMA;

        $existentes = [];
        foreach ($this->notas->listByMateriaAndGestion($materiaId, $gestion->id) as $nota) {
            $existentes[$nota->inscripcion->id][$nota->numeroExamen] = $nota->valor;
        }

        $ponderaciones = $gestion->configuracion?->ponderacionesExamenes;
        $filas = [];
        foreach ($this->asignacionesGrupo->listByMateriaAndGrupo($materiaId, $grupoId) as $asignacion) {
            $inscripcion = $asignacion->inscripcion;
            $valores = [];
            for ($examen = 1; $examen <= $cantidadExamenes; $examen++) {
                $valores[$examen] = $existentes[$inscripcion->id][$examen] ?? null;
            }

            $eval = $this->calc->evaluarMateria($valores, $ponderaciones, $cantidadExamenes, $notaMinima);

            $filas[] = [
                'inscripcion' => $inscripcion,
                'valores' => $valores,
                'promedio' => $eval['promedio'],
                'aprobado' => $eval['aprobado'],
            ];
        }

        return [
            'materia' => $materia,
            'grupo' => $grupo,
            'gestion' => $gestion,
            'cantidadExamenes' => $cantidadExamenes,
            'notaMinima' => $notaMinima,
            'filas' => $filas,
        ];
    }
}
