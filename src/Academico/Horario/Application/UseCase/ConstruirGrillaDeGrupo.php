<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Horario\Domain\Entity\Horario;

/**
 * Construye una grilla semanal (tipo boleta de inscripcion) a partir de los
 * horarios de un grupo: filas = franjas horarias, columnas = dias, celdas con
 * la materia y su aula. Asigna un color por materia para la visualizacion.
 */
final readonly class ConstruirGrillaDeGrupo
{
    private const DIAS_ORDEN = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

    private const PALETA = ['#fff3b0', '#d9f99d', '#a7f3d0', '#bfdbfe', '#fbcfe8', '#fed7aa', '#e9d5ff', '#fecaca'];

    /**
     * @param list<Horario> $horarios
     *
     * @return array{
     *     dias: list<string>,
     *     filas: list<array{inicio:string, fin:string, celdas: array<string, array{sigla:string, aula:string, color:string}|null>}>,
     *     leyenda: list<array{materia:string, color:string}>
     * }
     */
    public function execute(array $horarios): array
    {
        $colores = [];
        $leyenda = [];
        $franjas = [];
        $diasUsados = [];
        $celdas = [];
        $indiceColor = 0;

        foreach ($horarios as $horario) {
            $materiaId = $horario->materia->id ?? 0;
            if (!isset($colores[$materiaId])) {
                $color = self::PALETA[$indiceColor % count(self::PALETA)];
                $colores[$materiaId] = $color;
                $leyenda[] = ['materia' => $horario->materia->nombre, 'color' => $color];
                $indiceColor++;
            }

            $inicio = $horario->horaInicio->format('H:i');
            $fin = $horario->horaFin->format('H:i');
            $franjaKey = $inicio . '-' . $fin;

            if (!isset($franjas[$franjaKey])) {
                $franjas[$franjaKey] = [
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'orden' => (int) $horario->horaInicio->format('Hi'),
                ];
            }

            $dia = $horario->dia;
            $diasUsados[$dia] = true;

            $celdas[$franjaKey][$dia] = [
                'sigla' => $horario->materia->codigo ?? $horario->materia->nombre,
                'aula' => $horario->aula->codigo,
                'color' => $colores[$materiaId],
            ];
        }

        uasort($franjas, static fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        $dias = array_values(array_filter(self::DIAS_ORDEN, static fn (string $dia): bool => isset($diasUsados[$dia])));

        $filas = [];
        foreach ($franjas as $franjaKey => $franja) {
            $fila = ['inicio' => $franja['inicio'], 'fin' => $franja['fin'], 'celdas' => []];
            foreach ($dias as $dia) {
                $fila['celdas'][$dia] = $celdas[$franjaKey][$dia] ?? null;
            }
            $filas[] = $fila;
        }

        return [
            'dias' => $dias,
            'filas' => $filas,
            'leyenda' => $leyenda,
        ];
    }
}
