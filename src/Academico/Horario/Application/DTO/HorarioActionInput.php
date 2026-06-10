<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\DTO;

final readonly class HorarioActionInput
{
    public function __construct(
        public int $horarioId,
        public ?int $actorUserId,
    ) {
    }
}
