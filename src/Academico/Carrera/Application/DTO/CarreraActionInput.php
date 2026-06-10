<?php

declare(strict_types=1);

namespace App\Academico\Carrera\Application\DTO;

final readonly class CarreraActionInput
{
    public function __construct(
        public int $carreraId,
        public ?int $actorUserId,
    ) {
    }
}
