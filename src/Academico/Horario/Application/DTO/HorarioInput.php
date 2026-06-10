<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\DTO;

final readonly class HorarioInput
{
    public function __construct(
        public int $grupoId,
        public int $materiaId,
        public int $aulaId,
        public string $dia,
        public ?\DateTimeImmutable $horaInicio,
        public ?\DateTimeImmutable $horaFin,
        public ?int $actorUserId,
    ) {
    }
}
