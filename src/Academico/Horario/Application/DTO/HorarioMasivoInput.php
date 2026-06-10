<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\DTO;

/**
 * Entrada del asistente de asignacion masiva de horarios.
 *
 * Paso 1 (turno): se define el turno y su rango [horaInicio, horaFin], mas la
 *   duracion de cada clase, lo que segmenta el turno en franjas consecutivas.
 * Paso 2 (materias): cada materia ocupa una franja y se le asigna un aula.
 * Paso 3 (grupo y dias): el grupo y los dias sobre los que se replica todo.
 *
 * No interviene docente ni estudiante (se asignan luego, de forma independiente).
 */
final readonly class HorarioMasivoInput
{
    /**
     * @param list<string>                                      $dias    Dias de la semana (LUNES, MARTES, ...).
     * @param list<array{materiaId:int, aulaId:int, orden:int}> $bloques Materias a programar, en orden.
     */
    public function __construct(
        public int $grupoId,
        public string $turno,
        public array $dias,
        public ?\DateTimeImmutable $horaInicio,
        public ?\DateTimeImmutable $horaFin,
        public int $duracionMinutos,
        public int $descansoMinutos,
        public array $bloques,
        public bool $reemplazar,
        public ?int $actorUserId,
    ) {
    }
}
