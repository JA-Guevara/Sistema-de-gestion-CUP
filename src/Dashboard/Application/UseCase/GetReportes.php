<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Dashboard\Infrastructure\Persistence\ReporteRepository;
use App\Gestion\Domain\Entity\Gestion;

/**
 * Arma el payload analítico del Dashboard/Reportes para una gestión y filtros
 * opcionales (carrera, materia, docente). Calcula KPIs, datasets de gráficos y
 * el detalle de postulantes.
 *
 * Regla de aprobación global (acordada): el promedio de los promedios por
 * materia del estudiante debe ser >= notaMínima de la gestión. Solo se
 * considera "aprobado/reprobado" si el estudiante tiene completos todos sus
 * exámenes (cantidadExamenes por materia); si no, queda "incompleto".
 */
final readonly class GetReportes
{
    public function __construct(private ReporteRepository $repo)
    {
    }

    /** @return array<string, mixed> */
    public function execute(Gestion $gestion, ?int $carreraId = null, ?int $materiaId = null, ?int $docenteId = null): array
    {
        $gestionId = (int) $gestion->id;
        $notaMinima = $gestion->configuracion?->notaMinimaAprobacion ?? 60;
        $cantidadExamenes = $gestion->configuracion?->cantidadExamenes ?? 3;
        $maxPorGrupo = $gestion->configuracion?->maxEstudiantesPorGrupo ?? 70;

        $rows = $this->repo->notasEstudiantes($gestionId, $carreraId, $materiaId, $docenteId);

        // Agrupar notas por estudiante y materia.
        $estudiantes = [];
        $materiaNombre = [];
        foreach ($rows as $r) {
            $insId = (int) $r['insId'];
            if (!isset($estudiantes[$insId])) {
                $estudiantes[$insId] = [
                    'ci' => $r['ci'],
                    'nombres' => $r['nombres'],
                    'apellidos' => $r['apellidos'],
                    'email' => $r['email'] ?? '',
                    'carrera' => $r['carreraNombre'] ?? '—',
                    'materias' => [],
                ];
            }
            $matId = (int) $r['materiaId'];
            $materiaNombre[$matId] = $r['materiaNombre'];
            $estudiantes[$insId]['materias'][$matId][] = (int) $r['valor'];
        }

        $aprobados = 0;
        $reprobados = 0;
        $incompletos = 0;
        $sumaGlobal = 0;
        $countCompletos = 0;
        $estadoPorIns = [];
        $detalle = [];
        $matAcum = []; // matId => [nombre, sumProm, count, aprob, reprob]

        foreach ($estudiantes as $insId => $e) {
            $promMaterias = [];
            $notasStudent = [];
            $completo = count($e['materias']) > 0;

            foreach ($e['materias'] as $matId => $valores) {
                $promMat = (int) round(array_sum($valores) / count($valores));
                $promMaterias[] = $promMat;
                $notasStudent[$materiaNombre[$matId]] = $promMat;
                $matCompleta = count($valores) >= $cantidadExamenes;
                if (!$matCompleta) {
                    $completo = false;
                }

                if (!isset($matAcum[$matId])) {
                    $matAcum[$matId] = ['nombre' => $materiaNombre[$matId], 'sumProm' => 0, 'count' => 0, 'aprob' => 0, 'reprob' => 0];
                }
                $matAcum[$matId]['sumProm'] += $promMat;
                $matAcum[$matId]['count']++;
                if ($matCompleta) {
                    if ($promMat >= $notaMinima) {
                        $matAcum[$matId]['aprob']++;
                    } else {
                        $matAcum[$matId]['reprob']++;
                    }
                }
            }

            $global = $promMaterias !== [] ? (int) round(array_sum($promMaterias) / count($promMaterias)) : 0;

            if (!$completo) {
                $estado = 'INCOMPLETO';
                $incompletos++;
            } elseif ($global >= $notaMinima) {
                $estado = 'APROBADO';
                $aprobados++;
                $sumaGlobal += $global;
                $countCompletos++;
            } else {
                $estado = 'REPROBADO';
                $reprobados++;
                $sumaGlobal += $global;
                $countCompletos++;
            }

            $estadoPorIns[$insId] = $estado;
            $detalle[] = [
                'ci' => $e['ci'],
                'nombre' => trim($e['apellidos'] . ' ' . $e['nombres']),
                'email' => $e['email'],
                'carrera' => $e['carrera'],
                'materias' => count($e['materias']),
                'notas' => $notasStudent,
                'promedio' => $completo ? $global : null,
                'estado' => $estado,
            ];
        }

        usort($detalle, static fn (array $a, array $b): int => strcmp((string) $a['nombre'], (string) $b['nombre']));

        // Promedio + aprobados/reprobados por materia.
        $promedioPorMateria = [];
        foreach ($matAcum as $m) {
            $promedioPorMateria[] = [
                'materia' => $m['nombre'],
                'promedio' => $m['count'] > 0 ? (int) round($m['sumProm'] / $m['count']) : 0,
                'aprobados' => $m['aprob'],
                'reprobados' => $m['reprob'],
            ];
        }
        usort($promedioPorMateria, static fn (array $a, array $b): int => strcmp((string) $a['materia'], (string) $b['materia']));

        // Estadísticas por grupo (completas) + top grupos por aprobados.
        $grupoStats = [];
        $grupoNombre = [];
        $vistos = [];
        foreach ($this->repo->asignacionEstudianteGrupo($gestionId) as $a) {
            $insId = (int) $a['insId'];
            $grupoId = (int) $a['grupoId'];
            $grupoNombre[$grupoId] = $a['grupo'];
            $key = $grupoId . ':' . $insId;
            if (isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;
            if (!isset($grupoStats[$grupoId])) {
                $grupoStats[$grupoId] = ['total' => 0, 'aprob' => 0, 'reprob' => 0, 'incomp' => 0];
            }
            $grupoStats[$grupoId]['total']++;
            $est = $estadoPorIns[$insId] ?? 'INCOMPLETO';
            if ($est === 'APROBADO') {
                $grupoStats[$grupoId]['aprob']++;
            } elseif ($est === 'REPROBADO') {
                $grupoStats[$grupoId]['reprob']++;
            } else {
                $grupoStats[$grupoId]['incomp']++;
            }
        }

        $grupos = [];
        $topGrupos = [];
        foreach ($grupoStats as $gid => $s) {
            $nombre = $grupoNombre[$gid] ?? ('#' . $gid);
            $grupos[] = ['grupo' => $nombre, 'total' => $s['total'], 'aprobados' => $s['aprob'], 'reprobados' => $s['reprob'], 'incompletos' => $s['incomp']];
            $topGrupos[] = ['grupo' => $nombre, 'aprobados' => $s['aprob']];
        }
        usort($grupos, static fn (array $a, array $b): int => strcmp((string) $a['grupo'], (string) $b['grupo']));
        usort($topGrupos, static fn (array $a, array $b): int => $b['aprobados'] - $a['aprobados']);
        $topGrupos = array_slice($topGrupos, 0, 8);

        // Carga docente.
        $docentesPorGrupo = array_map(static fn (array $d): array => [
            'docente' => trim(((string) $d['lastName']) . ' ' . ((string) $d['firstName'])),
            'grupos' => (int) $d['grupos'],
        ], $this->repo->docentesPorGrupo($gestionId, $materiaId, $docenteId));

        // Inscritos por carrera.
        $inscritosPorCarrera = array_map(static fn (array $c): array => [
            'carrera' => (string) $c['carrera'],
            'total' => (int) $c['total'],
        ], $this->repo->inscritosPorCarrera($gestionId));

        $totalInscritos = $this->repo->totalInscritosEstudiantes($gestionId, $carreraId);
        $gruposHabilitados = $maxPorGrupo > 0 ? (int) ceil($totalInscritos / $maxPorGrupo) : 0;
        $promedioGeneral = $countCompletos > 0 ? (int) round($sumaGlobal / $countCompletos) : 0;
        $pctAprobacion = ($aprobados + $reprobados) > 0 ? round($aprobados / ($aprobados + $reprobados) * 100, 1) : 0;

        return [
            'meta' => [
                'gestionId' => $gestionId,
                'gestionCodigo' => $gestion->codigo,
                'gestionNombre' => $gestion->nombre,
                'notaMinima' => $notaMinima,
                'cantidadExamenes' => $cantidadExamenes,
                'materias' => array_map(static fn (array $m): string => (string) $m['materia'], $promedioPorMateria),
                'filtros' => ['carrera' => $carreraId, 'materia' => $materiaId, 'docente' => $docenteId],
            ],
            'kpis' => [
                'inscritos' => $totalInscritos,
                'evaluados' => count($estudiantes),
                'aprobados' => $aprobados,
                'reprobados' => $reprobados,
                'incompletos' => $incompletos,
                'pctAprobacion' => $pctAprobacion,
                'promedioGeneral' => $promedioGeneral,
                'grupos' => $gruposHabilitados,
                'docentes' => $this->repo->totalDocentes($gestionId),
            ],
            'estadoAprobacion' => [
                'aprobados' => $aprobados,
                'reprobados' => $reprobados,
                'incompletos' => $incompletos,
            ],
            'promedioPorMateria' => $promedioPorMateria,
            'inscritosPorCarrera' => $inscritosPorCarrera,
            'topGrupos' => $topGrupos,
            'grupos' => $grupos,
            'docentesPorGrupo' => $docentesPorGrupo,
            'detalle' => $detalle,
        ];
    }
}
