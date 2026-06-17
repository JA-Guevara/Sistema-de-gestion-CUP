<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Dashboard\Infrastructure\Persistence\ReporteRepository;
use App\Gestion\Domain\Entity\Gestion;
use App\Notas\Domain\Service\PromedioCalculator;

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
    public function __construct(private ReporteRepository $repo, private PromedioCalculator $calc, private MateriaRepository $materias)
    {
    }

    /** @return array<string, mixed> */
    public function execute(Gestion $gestion, ?int $carreraId = null, ?int $materiaId = null, ?int $docenteId = null): array
    {
        $gestionId = (int) $gestion->id;
        $notaMinima = $gestion->configuracion?->notaMinimaAprobacion ?? 60;
        $cantidadExamenes = $gestion->configuracion?->cantidadExamenes ?? 3;
        $maxPorGrupo = $gestion->configuracion?->maxEstudiantesPorGrupo ?? 70;
        $ponderaciones = $gestion->configuracion?->ponderacionesExamenes;
        // Materias del CUP esperadas (activas): el estudiante debe tenerlas TODAS.
        $materiasEsperadas = count($this->materias->listActive());

        $estudiantesBase = [];
        foreach ($this->repo->estudiantesDeGestion($gestionId, $carreraId) as $r) {
            $insId = (int) $r['insId'];
            $estudiantesBase[$insId] = [
                'ci' => (string) $r['ci'],
                'nombres' => (string) $r['nombres'],
                'apellidos' => (string) $r['apellidos'],
                'email' => (string) ($r['email'] ?? ''),
                'carrera' => (string) ($r['carreraNombre'] ?? '—'),
                'estadoInscripcion' => (string) ($r['estado'] ?? EstadoInscripcion::BORRADOR),
                'materias' => [],
                'asignaciones' => [],
            ];
        }

        $asignaciones = $this->repo->asignacionesEstudianteMateria($gestionId, $carreraId, $materiaId, $docenteId);
        $materiaNombre = [];
        $grupoNombre = [];
        foreach ($asignaciones as $a) {
            $insId = (int) $a['insId'];
            if (!isset($estudiantesBase[$insId])) {
                continue;
            }

            $matId = (int) $a['materiaId'];
            $materiaNombre[$matId] = (string) $a['materiaNombre'];
            $grupoNombre[$matId] = (string) $a['grupoCodigo'];
            $docenteNombre = trim(((string) ($a['lastName'] ?? '')) . ' ' . ((string) ($a['firstName'] ?? '')));

            $estudiantesBase[$insId]['asignaciones'][$matId] = [
                'materiaId' => $matId,
                'materia' => (string) $a['materiaNombre'],
                'grupo' => (string) $a['grupoCodigo'],
                'docente' => $docenteNombre !== '' ? $docenteNombre : null,
                'notas' => [],
            ];
            $estudiantesBase[$insId]['materias'][$matId] = [];
        }

        $rows = $this->repo->notasEstudiantes($gestionId, $carreraId, $materiaId, $docenteId);
        foreach ($rows as $r) {
            $insId = (int) $r['insId'];
            if (!isset($estudiantesBase[$insId])) {
                continue;
            }
            $matId = (int) $r['materiaId'];
            $materiaNombre[$matId] = (string) $r['materiaNombre'];
            $estudiantesBase[$insId]['materias'][$matId][(int) $r['numeroExamen']] = (int) $r['valor'];
            if (!isset($estudiantesBase[$insId]['asignaciones'][$matId])) {
                $estudiantesBase[$insId]['asignaciones'][$matId] = [
                    'materiaId' => $matId,
                    'materia' => (string) $r['materiaNombre'],
                    'grupo' => null,
                    'docente' => null,
                    'notas' => [],
                ];
            }
            $estudiantesBase[$insId]['asignaciones'][$matId]['notas'][] = (int) $r['valor'];
        }

        $aprobados = 0;
        $reprobados = 0;
        $incompletos = 0;
        $sumaGlobal = 0;
        $countCompletos = 0;
        $evaluados = 0;
        $estadoPorIns = [];
        $detalle = [];
        $matAcum = []; // matId => [nombre, sumProm, count, aprob, reprob]

        foreach ($estudiantesBase as $insId => $e) {
            $promMaterias = [];
            $notasStudent = [];
            $completo = count($e['materias']) > 0;
            // APROBADO del CUP = todas las materias completas y CADA una >= notaMinima.
            $aproboTodas = true;
            $tieneAsignaciones = $e['asignaciones'] !== [];

            foreach ($e['materias'] as $matId => $valores) {
                if ($valores === []) {
                    $completo = false;
                    $aproboTodas = false;
                    continue;
                }

                $valoresPorExamen = [];
                for ($ex = 1; $ex <= $cantidadExamenes; $ex++) {
                    $valoresPorExamen[$ex] = $valores[$ex] ?? null;
                }
                $eval = $this->calc->evaluarMateria($valoresPorExamen, $ponderaciones, $cantidadExamenes, $notaMinima);
                $promMat = (int) $eval['promedio'];
                $promMaterias[] = $promMat;
                $notasStudent[$materiaNombre[$matId]] = $promMat;
                $matCompleta = $eval['contadas'] >= $cantidadExamenes;
                if (!$matCompleta) {
                    $completo = false;
                }
                if ($promMat < $notaMinima) {
                    $aproboTodas = false;
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

            // Si le faltan materias del CUP (tiene menos que las activas), no esta
            // completo: no puede aprobar aunque las que tiene esten bien.
            if ($materiasEsperadas > 0 && count($e['materias']) < $materiasEsperadas) {
                $completo = false;
            }

            $global = $promMaterias !== [] ? (int) round(array_sum($promMaterias) / count($promMaterias)) : null;

            if ($global === null) {
                $estado = 'INCOMPLETO';
                $incompletos++;
            } elseif ($completo && $aproboTodas) {
                $estado = 'APROBADO';
                $aprobados++;
                $sumaGlobal += $global;
                $countCompletos++;
                $evaluados++;
            } elseif ($completo) {
                $estado = 'REPROBADO';
                $reprobados++;
                $sumaGlobal += $global;
                $countCompletos++;
                $evaluados++;
            } else {
                $estado = 'INCOMPLETO';
                $incompletos++;
            }

            $estadoPorIns[$insId] = $estado;
            $detalle[] = [
                'ci' => $e['ci'],
                'nombre' => trim($e['apellidos'] . ' ' . $e['nombres']),
                'email' => $e['email'],
                'carrera' => $e['carrera'],
                'estadoInscripcion' => $e['estadoInscripcion'],
                'materias' => count($e['materias']),
                'asignaciones' => array_values($e['asignaciones']),
                'notas' => $notasStudent,
                'promedio' => $global,
                'estado' => $estado,
                'tieneAsignaciones' => $tieneAsignaciones,
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

        $postulacionesEstudiantes = $this->estadoMap($this->repo->postulacionesPorEstado($gestionId, TipoPostulacion::ESTUDIANTE));
        $postulacionesDocentes = $this->estadoMap($this->repo->postulacionesPorEstado($gestionId, TipoPostulacion::DOCENTE));
        $resumenEstudiantes = $this->estadoResumen($postulacionesEstudiantes);
        $resumenDocentes = $this->estadoResumen($postulacionesDocentes);

        $totalInscritos = $this->repo->totalPostulaciones($gestionId, TipoPostulacion::ESTUDIANTE);
        $totalDocentesPostulaciones = $this->repo->totalPostulaciones($gestionId, TipoPostulacion::DOCENTE);
        $totalDocentesConAsignacion = $this->repo->totalDocentes($gestionId);
        $entrevistasAgendadas = $this->repo->entrevistasDocentesAgendadas($gestionId);
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
                'evaluados' => $evaluados,
                'aprobados' => $aprobados,
                'reprobados' => $reprobados,
                'incompletos' => $incompletos,
                'pctAprobacion' => $pctAprobacion,
                'promedioGeneral' => $promedioGeneral,
                'grupos' => $gruposHabilitados,
                'docentes' => $totalDocentesConAsignacion,
                'postulacionesTotales' => $totalInscritos,
                'postulacionesAceptadas' => $resumenEstudiantes['aprobados'],
                'postulacionesRechazadas' => $resumenEstudiantes['rechazados'],
                'postulacionesPresentadas' => $resumenEstudiantes['presentados'],
                'postulacionesBorrador' => $resumenEstudiantes['borrador'],
                'estudiantesAprobados' => $resumenEstudiantes['aprobados'],
                'estudiantesRechazados' => $resumenEstudiantes['rechazados'],
                'estudiantesEnProceso' => $resumenEstudiantes['enProceso'],
                'postulacionesDocentes' => $totalDocentesPostulaciones,
                'docentesAprobados' => $resumenDocentes['aprobados'],
                'docentesRechazados' => $resumenDocentes['rechazados'],
                'docentesEnProceso' => $resumenDocentes['enProceso'],
            ],
            'estadoAprobacion' => [
                'aprobados' => $aprobados,
                'reprobados' => $reprobados,
                'incompletos' => $incompletos,
            ],
            'postulaciones' => [
                'estudiantes' => $postulacionesEstudiantes,
                'docentes' => $postulacionesDocentes,
            ],
            'postulacionesResumen' => [
                'estudiantes' => $resumenEstudiantes,
                'docentes' => $resumenDocentes,
            ],
            'promedioPorMateria' => $promedioPorMateria,
            'inscritosPorCarrera' => $inscritosPorCarrera,
            'topGrupos' => $topGrupos,
            'grupos' => $grupos,
            'docentesPorGrupo' => $docentesPorGrupo,
            'docentes' => [
                'totalDocentes' => $totalDocentesConAsignacion,
                'totalPostulaciones' => $totalDocentesPostulaciones,
                'aprobados' => $resumenDocentes['aprobados'],
                'rechazados' => $resumenDocentes['rechazados'],
                'enProceso' => $resumenDocentes['enProceso'],
                'entrevistasAgendadas' => $entrevistasAgendadas,
                'postulaciones' => array_map(static fn (string $estado): array => [
                    'estado' => $estado,
                    'total' => (int) ($postulacionesDocentes[$estado] ?? 0),
                ], array_keys($postulacionesDocentes)),
                'carga' => array_map(static fn (array $d): array => [
                    'docente' => trim(((string) $d['lastName']) . ' ' . ((string) $d['firstName'])),
                    'materias' => (int) $d['materias'],
                    'grupos' => (int) $d['grupos'],
                ], $this->repo->cargaDocentes($gestionId, $docenteId)),
            ],
            'detalle' => $detalle,
            'postulantes' => [
                'totalEstudiantes' => $totalInscritos,
                'totalDocentes' => $totalDocentesPostulaciones,
                'estudiantes' => array_values($estudiantesBase),
            ],
        ];
    }

    /**
     * @param list<array{estado:string,total:int|string}> $rows
     * @return array<string,int>
     */
    private function estadoMap(array $rows): array
    {
        $map = array_fill_keys($this->estadoOrder(), 0);
        foreach ($rows as $row) {
            $estado = (string) $row['estado'];
            if (array_key_exists($estado, $map)) {
                $map[$estado] = (int) $row['total'];
            }
        }

        return $map;
    }

    /**
     * @param array<string,int> $map
     * @return array{aprobados:int,rechazados:int,enProceso:int,borrador:int,presentados:int}
     */
    private function estadoResumen(array $map): array
    {
        return [
            'aprobados' => $this->sumEstados($map, [EstadoInscripcion::CONFIRMADA, EstadoInscripcion::COMPLETADA]),
            'rechazados' => $this->sumEstados($map, [EstadoInscripcion::RECHAZADA, EstadoInscripcion::ANULADA]),
            'enProceso' => $this->sumEstados($map, [EstadoInscripcion::PRESENTADA, EstadoInscripcion::VALIDADA, EstadoInscripcion::PENDIENTE]),
            'borrador' => (int) ($map[EstadoInscripcion::BORRADOR] ?? 0),
            'presentados' => $this->sumEstados($map, [EstadoInscripcion::PRESENTADA, EstadoInscripcion::VALIDADA, EstadoInscripcion::CONFIRMADA, EstadoInscripcion::COMPLETADA, EstadoInscripcion::PENDIENTE]),
        ];
    }

    /**
     * @param array<string,int> $map
     * @param list<string> $states
     */
    private function sumEstados(array $map, array $states): int
    {
        $total = 0;
        foreach ($states as $state) {
            $total += (int) ($map[$state] ?? 0);
        }

        return $total;
    }

    /** @return list<string> */
    private function estadoOrder(): array
    {
        return [
            EstadoInscripcion::CONFIRMADA,
            EstadoInscripcion::VALIDADA,
            EstadoInscripcion::PRESENTADA,
            EstadoInscripcion::BORRADOR,
            EstadoInscripcion::RECHAZADA,
            EstadoInscripcion::ANULADA,
            EstadoInscripcion::PENDIENTE,
            EstadoInscripcion::COMPLETADA,
        ];
    }
}
