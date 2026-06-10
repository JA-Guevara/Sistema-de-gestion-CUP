<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\DTO;

final readonly class AulaActionInput
{
    public function __construct(
        public int $aulaId,
        public ?int $actorUserId,
    ) {
    }
}
