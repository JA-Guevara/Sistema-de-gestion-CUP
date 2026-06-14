<?php

declare(strict_types=1);

namespace App\Notas\Application\DTO;

final readonly class AsignacionInput
{
    public function __construct(
        public int $materiaId,
        public int $grupoId,
        public int $docenteId,
        public ?int $actorUserId,
    ) {
    }
}
