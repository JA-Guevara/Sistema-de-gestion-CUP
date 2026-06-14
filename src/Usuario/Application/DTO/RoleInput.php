<?php

declare(strict_types=1);

namespace App\Usuario\Application\DTO;

final readonly class RoleInput
{
    /** @param list<int> $permissionIds */
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $active,
        public array $permissionIds,
        public ?int $actorUserId,
    ) {
    }
}
