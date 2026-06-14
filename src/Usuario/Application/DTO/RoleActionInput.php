<?php

declare(strict_types=1);

namespace App\Usuario\Application\DTO;

final readonly class RoleActionInput
{
    public function __construct(
        public int $roleId,
        public ?int $actorUserId,
    ) {
    }
}
