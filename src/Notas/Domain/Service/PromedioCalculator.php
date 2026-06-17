<?php

declare(strict_types=1);

namespace App\Notas\Domain\Service;

/**
 * Calculo del promedio de una materia y del promedio general del estudiante.
 *
 * Soporta ponderacion por examen: si la gestion configura pesos que suman 100,
 * la nota de la materia es la suma ponderada (Σ valor_e · peso_e / 100). Si NO
 * hay ponderaciones validas, cae al promedio aritmetico simple (comportamiento
 * historico, no rompe nada). La ponderacion solo aplica cuando estan TODOS los
 * examenes; si falta alguno, se usa el promedio simple de los presentes.
 */
final readonly class PromedioCalculator
{
    /**
     * @param array<int, int|null> $valores valor por numero de examen (1..cantidadExamenes), null si falta
     * @param array<int|string, int|float>|null $ponderaciones peso por examen (suman 100) o null/[] => simple
     * @return array{promedio: int|null, aprobado: bool|null, contadas: int}
     */
    public function evaluarMateria(array $valores, ?array $ponderaciones, int $cantidadExamenes, int $notaMinima): array
    {
        $suma = 0;
        $contadas = 0;
        for ($e = 1; $e <= $cantidadExamenes; $e++) {
            $valor = $valores[$e] ?? null;
            if ($valor !== null) {
                $suma += $valor;
                $contadas++;
            }
        }

        $completo = $contadas === $cantidadExamenes;
        $pesos = $this->ponderacionesValidas($ponderaciones, $cantidadExamenes);

        if ($completo && $pesos !== null) {
            $acumulado = 0.0;
            for ($e = 1; $e <= $cantidadExamenes; $e++) {
                $acumulado += ((int) $valores[$e]) * $pesos[$e];
            }
            $promedio = (int) round($acumulado / 100);
        } else {
            $promedio = $contadas > 0 ? (int) round($suma / $contadas) : null;
        }

        $aprobado = $completo ? $promedio >= $notaMinima : null;

        return ['promedio' => $promedio, 'aprobado' => $aprobado, 'contadas' => $contadas];
    }

    /**
     * Promedio general = promedio de los promedios por materia (no nulos).
     *
     * @param list<int|null> $promediosMateria
     */
    public function promedioGeneral(array $promediosMateria): ?int
    {
        $valores = array_values(array_filter($promediosMateria, static fn (?int $p): bool => $p !== null));

        return $valores !== [] ? (int) round(array_sum($valores) / count($valores)) : null;
    }

    /**
     * Normaliza las ponderaciones a [examen(int) => peso(float)] solo si hay
     * exactamente cantidadExamenes pesos y suman ~100; en caso contrario null.
     *
     * @param array<int|string, int|float>|null $ponderaciones
     * @return array<int, float>|null
     */
    private function ponderacionesValidas(?array $ponderaciones, int $cantidadExamenes): ?array
    {
        if ($ponderaciones === null || $ponderaciones === []) {
            return null;
        }

        $norm = [];
        foreach ($ponderaciones as $key => $valor) {
            $examen = is_int($key) ? $key : (int) preg_replace('/\D+/', '', (string) $key);
            if ($examen >= 1 && is_numeric($valor)) {
                $norm[$examen] = (float) $valor;
            }
        }

        $suma = 0.0;
        for ($e = 1; $e <= $cantidadExamenes; $e++) {
            if (!isset($norm[$e])) {
                return null;
            }
            $suma += $norm[$e];
        }

        return abs($suma - 100.0) <= 0.5 ? $norm : null;
    }
}
