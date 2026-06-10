<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\DTO;

final readonly class GrupoActionInput
{
    public function __construct(
        public int $grupoId,
        public ?int $actorUserId,
    ) {
    }
}
