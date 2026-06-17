<?php

declare(strict_types=1);

namespace App\Admision\Application\Service;

use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Gestion\Domain\Entity\Gestion;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Domain\Service\PromedioCalculator;
use App\Notas\Infrastructure\Persistence\NotaRepository;

/**
 * Evalua a TODOS los estudiantes CONFIRMADOS de una gestion: calcula su promedio
 * general (ponderado si la gestion lo configura) y su estado academico del CUP.
 *
 * REGLA CLAVE: el estudiante solo APRUEBA si tiene TODAS las materias del CUP
 * (las materias activas) completas y cada una >= nota minima. Si le falta alguna
 * materia (no asignada o sin notas), queda PENDIENTE (no aprueba ni reprueba aun).
 *
 * Lo reutilizan EjecutarAdmisionFinal (para adjudicar cupos) y VerAdmision.
 */
final readonly class EvaluadorAcademico
{
    public const APROBADO = 'APROBADO';
    public const REPROBADO = 'REPROBADO';
    public const PENDIENTE = 'PENDIENTE';

    public function __construct(
        private InscripcionRepository $inscripciones,
        private NotaRepository $notas,
        private MateriaRepository $materias,
        private PromedioCalculator $calc,
    ) {
    }

    /**
     * @return list<array{insc: Inscripcion, promedio: int|null, estado: string}>
     *         Ordenado por promedio descendente (desempate por apellidos).
     */
    public function evaluar(Gestion $gestion): array
    {
        $gestionId = (int) $gestion->id;
        $config = $gestion->configuracion;
        $cantidadExamenes = max(1, $config?->cantidadExamenes ?? 3);
        $notaMinima = $config?->notaMinimaAprobacion ?? 60;
        $ponderaciones = $config?->ponderacionesExamenes;

        // Materias ESPERADAS del CUP = todas las materias activas. El estudiante
        // debe tenerlas todas para aprobar.
        $materiasEsperadas = array_map(
            static fn ($m): int => (int) $m->id,
            $this->materias->listActive(),
        );

        $notasMap = [];
        foreach ($this->notas->listValoresByGestion($gestionId) as $row) {
            $notasMap[$row['insId']][$row['materiaId']][$row['numeroExamen']] = $row['valor'];
        }

        $confirmados = $this->inscripciones->listByGestionTipoEstado(
            $gestionId,
            TipoPostulacion::ESTUDIANTE,
            EstadoInscripcion::CONFIRMADA,
        );

        $resultado = [];
        foreach ($confirmados as $insc) {
            $resultado[] = $this->evaluarEstudiante($insc, $notasMap, $materiasEsperadas, $ponderaciones, $cantidadExamenes, $notaMinima);
        }

        usort($resultado, static function (array $a, array $b): int {
            $pa = $a['promedio'] ?? -1;
            $pb = $b['promedio'] ?? -1;

            return $pa !== $pb ? $pb <=> $pa : strcmp($a['insc']->apellidos, $b['insc']->apellidos);
        });

        return $resultado;
    }

    /**
     * @param array<int, array<int, array<int, int>>> $notasMap
     * @param list<int> $materiasEsperadas
     * @param array<string,float>|null $ponderaciones
     * @return array{insc: Inscripcion, promedio: int|null, estado: string}
     */
    private function evaluarEstudiante(
        Inscripcion $insc,
        array $notasMap,
        array $materiasEsperadas,
        ?array $ponderaciones,
        int $cantidadExamenes,
        int $notaMinima,
    ): array {
        if ($materiasEsperadas === []) {
            return ['insc' => $insc, 'promedio' => null, 'estado' => self::PENDIENTE];
        }

        $insId = (int) $insc->id;
        $promedios = [];
        $completo = true;
        $aproboTodas = true;

        foreach ($materiasEsperadas as $materiaId) {
            $valores = [];
            for ($e = 1; $e <= $cantidadExamenes; $e++) {
                $valores[$e] = $notasMap[$insId][$materiaId][$e] ?? null;
            }
            $eval = $this->calc->evaluarMateria($valores, $ponderaciones, $cantidadExamenes, $notaMinima);
            if ($eval['aprobado'] === null) {
                $completo = false;
                $aproboTodas = false;
            } elseif ($eval['aprobado'] === false) {
                $aproboTodas = false;
            }
            if ($eval['promedio'] !== null) {
                $promedios[] = $eval['promedio'];
            }
        }

        $estado = !$completo ? self::PENDIENTE : ($aproboTodas ? self::APROBADO : self::REPROBADO);

        return ['insc' => $insc, 'promedio' => $this->calc->promedioGeneral($promedios), 'estado' => $estado];
    }
}
