<?php

declare(strict_types=1);

namespace App\Usuario\Application\DTO;

final readonly class UsuariosRolBulkInput
{
    /** @param list<int> $userIds */
    public function __construct(
        public int $roleId,
        public array $userIds,
        public ?int $actorUserId,
    ) {
    }
}
