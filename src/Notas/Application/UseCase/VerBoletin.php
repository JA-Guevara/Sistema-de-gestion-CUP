<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Notas\Domain\Service\PromedioCalculator;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\NotaRepository;

final readonly class VerBoletin
{
    // Alineados con GetReportes y el examen (3 examenes, nota minima 60) para que
    // el estado del boletin y el del reporte coincidan si faltara la config.
    private const DEFAULT_EXAMENES = 3;
    private const DEFAULT_NOTA_MINIMA = 60;

    public function __construct(
        private InscripcionRepository $inscripciones,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private NotaRepository $notas,
        private MateriaRepository $materias,
        private PromedioCalculator $calc,
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

        // Grupo asignado por materia (para mostrar el grupo en cada fila).
        $grupoPorMateria = [];
        foreach ($this->asignacionesGrupo->listByInscripcion($inscripcion->id) as $asignacion) {
            $grupoPorMateria[(int) $asignacion->materia->id] = $asignacion->grupo;
        }

        // Se evaluan TODAS las materias del CUP (las activas), no solo las
        // asignadas: si al estudiante le falta una materia, queda incompleto.
        $ponderaciones = $gestion->configuracion?->ponderacionesExamenes;
        $materias = [];
        foreach ($this->materias->listActive() as $materia) {
            $materiaId = (int) $materia->id;
            $valores = [];
            for ($examen = 1; $examen <= $cantidadExamenes; $examen++) {
                $valores[$examen] = $notasPorMateria[$materiaId][$examen] ?? null;
            }

            $eval = $this->calc->evaluarMateria($valores, $ponderaciones, $cantidadExamenes, $notaMinima);

            $materias[] = [
                'materia' => $materia,
                'grupo' => $grupoPorMateria[$materiaId] ?? null,
                'valores' => $valores,
                'promedio' => $eval['promedio'],
                'aprobado' => $eval['aprobado'],
            ];
        }

        // Estado FINAL del CUP: aprueba solo si TIENE materias, todas con notas
        // completas y CADA UNA >= notaMinima (regla estricta acordada).
        $completo = $materias !== [];
        $aproboTodas = true;
        $promedios = [];
        foreach ($materias as $m) {
            if ($m['aprobado'] === null) {
                $completo = false;
                $aproboTodas = false;
            } elseif ($m['aprobado'] === false) {
                $aproboTodas = false;
            }
            if ($m['promedio'] !== null) {
                $promedios[] = $m['promedio'];
            }
        }

        $promedioGeneral = $this->calc->promedioGeneral($promedios);
        $estadoFinal = !$completo ? 'INCOMPLETO' : ($aproboTodas ? 'APROBADO' : 'REPROBADO');

        return [
            'inscripcion' => $inscripcion,
            'gestion' => $gestion,
            'cantidadExamenes' => $cantidadExamenes,
            'notaMinima' => $notaMinima,
            'materias' => $materias,
            'estadoFinal' => $estadoFinal,
            'promedioGeneral' => $promedioGeneral,
        ];
    }
}
