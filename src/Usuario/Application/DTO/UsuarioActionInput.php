<?php

declare(strict_types=1);

namespace App\Usuario\Application\DTO;

final readonly class UsuarioActionInput
{
    public function __construct(
        public int $userId,
        public ?int $actorUserId,
    ) {
    }
}
