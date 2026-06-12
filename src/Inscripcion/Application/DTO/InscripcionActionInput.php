<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class InscripcionActionInput
{
    public function __construct(
        public int $inscripcionId,
        public ?int $actorUserId,
    ) {
    }
}
