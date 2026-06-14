<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\NotaRepository;

final readonly class VerPlanilla
{
    private const DEFAULT_EXAMENES = 2;
    private const DEFAULT_NOTA_MINIMA = 51;

    public function __construct(
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private NotaRepository $notas,
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
    public function execute(int $materiaId, int $grupoId): array
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

        $cantidadExamenes = max(1, $gestion->configuracion?->cantidadExamenes ?? self::DEFAULT_EXAMENES);
        $notaMinima = $gestion->configuracion?->notaMinimaAprobacion ?? self::DEFAULT_NOTA_MINIMA;

        $existentes = [];
        foreach ($this->notas->listByMateriaAndGestion($materiaId, $gestion->id) as $nota) {
            $existentes[$nota->inscripcion->id][$nota->numeroExamen] = $nota->valor;
        }

        $filas = [];
        foreach ($this->asignacionesGrupo->listByMateriaAndGrupo($materiaId, $grupoId) as $asignacion) {
            $inscripcion = $asignacion->inscripcion;
            $valores = [];
            $suma = 0;
            $contadas = 0;
            for ($examen = 1; $examen <= $cantidadExamenes; $examen++) {
                $valor = $existentes[$inscripcion->id][$examen] ?? null;
                $valores[$examen] = $valor;
                if ($valor !== null) {
                    $suma += $valor;
                    $contadas++;
                }
            }

            $promedio = $contadas > 0 ? (int) round($suma / $contadas) : null;
            $aprobado = $contadas === $cantidadExamenes ? $promedio >= $notaMinima : null;

            $filas[] = [
                'inscripcion' => $inscripcion,
                'valores' => $valores,
                'promedio' => $promedio,
                'aprobado' => $aprobado,
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
