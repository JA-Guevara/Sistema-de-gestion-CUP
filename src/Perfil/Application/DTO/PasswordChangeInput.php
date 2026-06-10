<?php

declare(strict_types=1);

namespace App\Perfil\Application\DTO;

final readonly class PasswordChangeInput
{
    public function __construct(
        public int $userId,
        public string $currentPassword,
        public string $newPassword,
        public string $confirmPassword,
    ) {
    }
}
