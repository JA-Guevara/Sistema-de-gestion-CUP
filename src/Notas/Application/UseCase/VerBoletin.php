<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\NotaRepository;

final readonly class VerBoletin
{
    private const DEFAULT_EXAMENES = 2;
    private const DEFAULT_NOTA_MINIMA = 51;

    public function __construct(
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private NotaRepository $notas,
    ) {
    }

    /**
     * @return array{
     *     inscripcion: \App\Inscripcion\Domain\Entity\Inscripcion,
     *     gestion: \App\Gestion\Domain\Entity\Gestion,
     *     cantidadExamenes: int,
     *     notaMinima: int,
     *     materias: list<array{materia: \App\Academico\Materia\Domain\Entity\Materia, grupo: \App\Academico\Grupo\Domain\Entity\Grupo, valores: array<int, int|null>, promedio: int|null, aprobado: bool|null}>
     * }|null
     */
    public function execute(int $userId): ?array
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            return null;
        }

        $inscripcion = $this->inscripciones->findConfirmadaByUserAndGestion($userId, $gestion->id, TipoPostulacion::ESTUDIANTE);
        if ($inscripcion === null) {
            return null;
        }

        $cantidadExamenes = max(1, $gestion->configuracion?->cantidadExamenes ?? self::DEFAULT_EXAMENES);
        $notaMinima = $gestion->configuracion?->notaMinimaAprobacion ?? self::DEFAULT_NOTA_MINIMA;

        $notasPorMateria = [];
        foreach ($this->notas->listByInscripcion($inscripcion->id) as $nota) {
            $notasPorMateria[$nota->materia->id][$nota->numeroExamen] = $nota->valor;
        }

        $materias = [];
        foreach ($this->asignacionesGrupo->listByInscripcion($inscripcion->id) as $asignacion) {
            $materiaId = $asignacion->materia->id;
            $valores = [];
            $suma = 0;
            $contadas = 0;
            for ($examen = 1; $examen <= $cantidadExamenes; $examen++) {
                $valor = $notasPorMateria[$materiaId][$examen] ?? null;
                $valores[$examen] = $valor;
                if ($valor !== null) {
                    $suma += $valor;
                    $contadas++;
                }
            }

            $promedio = $contadas > 0 ? (int) round($suma / $contadas) : null;
            $aprobado = $contadas === $cantidadExamenes ? $promedio >= $notaMinima : null;

            $materias[] = [
                'materia' => $asignacion->materia,
                'grupo' => $asignacion->grupo,
                'valores' => $valores,
                'promedio' => $promedio,
                'aprobado' => $aprobado,
            ];
        }

        return [
            'inscripcion' => $inscripcion,
            'gestion' => $gestion,
            'cantidadExamenes' => $cantidadExamenes,
            'notaMinima' => $notaMinima,
            'materias' => $materias,
        ];
    }
}
