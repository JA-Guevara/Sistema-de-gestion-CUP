<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\DTO;

final readonly class GenerateGruposInput
{
    public function __construct(
        public int $gestionId,
        public int $totalInscritos,
        public ?int $actorUserId,
    ) {
    }
}
