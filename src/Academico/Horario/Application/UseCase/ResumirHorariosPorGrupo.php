<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;

/**
 * Consolida los horarios en un resumen por grupo, para el historial.
 *
 * En vez de mostrar una fila por dia, agrupa todo el horario de cada grupo:
 * cuantas materias tiene, en que dias y en que rango horario.
 */
final readonly class ResumirHorariosPorGrupo
{
    public function __construct(private HorarioRepository $horarios)
    {
    }

    /**
     * @return list<array{
     *     grupo: Grupo,
     *     materias: int,
     *     dias: list<string>,
     *     horaInicio: ?\DateTimeImmutable,
     *     horaFin: ?\DateTimeImmutable,
     *     total: int
     * }>
     */
    public function execute(): array
    {
        $acumulado = [];

        foreach ($this->horarios->listAll() as $horario) {
            $grupoId = $horario->grupo->id ?? 0;

            if (!isset($acumulado[$grupoId])) {
                $acumulado[$grupoId] = [
                    'grupo' => $horario->grupo,
                    'materias' => [],
                    'dias' => [],
                    'horaInicio' => $horario->horaInicio,
                    'horaFin' => $horario->horaFin,
                    'total' => 0,
                ];
            }

            $acumulado[$grupoId]['materias'][$horario->materia->id ?? 0] = true;
            $acumulado[$grupoId]['dias'][$horario->dia] = true;
            $acumulado[$grupoId]['total']++;

            if ($horario->horaInicio < $acumulado[$grupoId]['horaInicio']) {
                $acumulado[$grupoId]['horaInicio'] = $horario->horaInicio;
            }

            if ($horario->horaFin > $acumulado[$grupoId]['horaFin']) {
                $acumulado[$grupoId]['horaFin'] = $horario->horaFin;
            }
        }

        $resumen = [];
        foreach ($acumulado as $fila) {
            $resumen[] = [
                'grupo' => $fila['grupo'],
                'materias' => count($fila['materias']),
                'dias' => array_keys($fila['dias']),
                'horaInicio' => $fila['horaInicio'],
                'horaFin' => $fila['horaFin'],
                'total' => $fila['total'],
            ];
        }

        return $resumen;
    }
}
