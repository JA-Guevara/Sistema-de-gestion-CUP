<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Horario\Domain\Entity\Horario;

/**
 * Agrupa los horarios de un grupo por materia, para mostrar una fila por
 * materia con sus dias/horas apiladas (estilo boleta de inscripcion),
 * en vez de repetir la materia una vez por dia.
 */
final readonly class AgruparHorariosPorMateria
{
    private const DIAS_ORDEN = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

    /**
     * @param list<Horario> $horarios
     *
     * @return list<array{
     *     materiaId: int,
     *     sigla: string,
     *     nombre: string,
     *     lineas: list<array{horarioId: int, dia: string, horaInicio: string, horaFin: string, aula: string}>
     * }>
     */
    public function execute(array $horarios): array
    {
        $porMateria = [];

        foreach ($horarios as $horario) {
            $materiaId = $horario->materia->id ?? 0;

            if (!isset($porMateria[$materiaId])) {
                $porMateria[$materiaId] = [
                    'materiaId' => $materiaId,
                    'sigla' => $horario->materia->codigo ?? $horario->materia->nombre,
                    'nombre' => $horario->materia->nombre,
                    'lineas' => [],
                ];
            }

            $orden = array_search($horario->dia, self::DIAS_ORDEN, true);

            $porMateria[$materiaId]['lineas'][] = [
                'horarioId' => $horario->id ?? 0,
                'dia' => $horario->dia,
                'diaOrden' => $orden === false ? 99 : $orden,
                'horaInicio' => $horario->horaInicio->format('H:i'),
                'horaFin' => $horario->horaFin->format('H:i'),
                'aula' => $horario->aula->codigo,
            ];
        }

        foreach ($porMateria as &$materia) {
            usort($materia['lineas'], static function (array $a, array $b): int {
                return [$a['diaOrden'], $a['horaInicio']] <=> [$b['diaOrden'], $b['horaInicio']];
            });
            // El campo de orden ya no se necesita en la vista.
            foreach ($materia['lineas'] as &$linea) {
                unset($linea['diaOrden']);
            }
            unset($linea);
        }
        unset($materia);

        usort($porMateria, static fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

        return $porMateria;
    }
}
