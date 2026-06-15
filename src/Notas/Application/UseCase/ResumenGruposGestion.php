<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;

/**
 * Arma, por cada grupo de la gestion, su estructura academica DERIVADA de lo
 * que ya existe (sin nuevas entidades): turno, aulas, dias y rango horario (de
 * Horario), materias y docentes (de AsignacionDocente/Horario), e inscritos vs
 * cupo. Alimenta las tarjetas de grupo de la pantalla de asignacion.
 */
final readonly class ResumenGruposGestion
{
    public function __construct(
        private GrupoRepository $grupos,
        private HorarioRepository $horarios,
        private AsignacionDocenteRepository $asignacionesDocente,
        private AsignacionGrupoRepository $asignacionesGrupo,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function execute(int $gestionId): array
    {
        $inscritosPorGrupo = $this->asignacionesGrupo->contarInscritosPorGrupo($gestionId);

        /** @var array<int, list<array{materia: string, docente: string}>> $docentesPorGrupo */
        $docentesPorGrupo = [];
        foreach ($this->asignacionesDocente->listByGestion($gestionId) as $ad) {
            $docentesPorGrupo[(int) $ad->grupo->id][] = [
                'materia' => $ad->materia->codigo . ' - ' . $ad->materia->nombre,
                'docente' => trim($ad->docente->firstName . ' ' . $ad->docente->lastName),
            ];
        }

        $resumen = [];
        foreach ($this->grupos->listByGestion($gestionId) as $grupo) {
            $gid = (int) $grupo->id;

            $aulas = [];
            $dias = [];
            $materias = [];
            $inicio = null;
            $fin = null;
            foreach ($this->horarios->listByGrupo($gid) as $h) {
                $aulas[$h->aula->codigo] = $h->aula->codigo;
                $dias[mb_strtoupper($h->dia)] = mb_strtoupper($h->dia);
                $materias[(int) $h->materia->id] = ['codigo' => $h->materia->codigo, 'nombre' => $h->materia->nombre];
                if ($inicio === null || $h->horaInicio < $inicio) {
                    $inicio = $h->horaInicio;
                }
                if ($fin === null || $h->horaFin > $fin) {
                    $fin = $h->horaFin;
                }
            }

            $inscritos = $inscritosPorGrupo[$gid] ?? 0;
            $cupo = $grupo->cupo;

            $resumen[] = [
                'grupo' => $grupo,
                'turno' => $grupo->turno,
                'aulas' => array_values($aulas),
                'dias' => array_values($dias),
                'horaInicio' => $inicio,
                'horaFin' => $fin,
                'materias' => array_values($materias),
                'docentes' => $docentesPorGrupo[$gid] ?? [],
                'inscritos' => $inscritos,
                'cupo' => $cupo,
                'disponibles' => max(0, $cupo - $inscritos),
            ];
        }

        return $resumen;
    }
}
