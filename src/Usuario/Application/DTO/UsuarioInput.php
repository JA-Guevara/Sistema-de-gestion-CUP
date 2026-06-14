<?php

declare(strict_types=1);

namespace App\Usuario\Application\DTO;

final readonly class UsuarioInput
{
    /** @param list<int> $roleIds */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $password,
        public bool $active,
        public array $roleIds,
        public ?int $actorUserId,
    ) {
    }
}
