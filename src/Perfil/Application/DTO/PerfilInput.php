<?php

declare(strict_types=1);

namespace App\Perfil\Application\DTO;

final readonly class PerfilInput
{
    public function __construct(
        public int $userId,
        public string $firstName,
        public string $lastName,
    ) {
    }
}
