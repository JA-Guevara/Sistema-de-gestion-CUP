<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\DTO;

final readonly class HorariosPorTurnoInput
{
    /** @param list<string> $dias */
    public function __construct(
        public int $grupoId,
        public int $materiaId,
        public int $aulaId,
        public array $dias,
        public string $turno,
        public ?\DateTimeImmutable $horaInicio,
        public ?\DateTimeImmutable $horaFin,
        public ?int $actorUserId,
    ) {
    }
}
