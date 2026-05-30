<?php

declare(strict_types=1);

namespace App\Auth\UI\Request;

final readonly class ResetPasswordRequest
{
    public function __construct(
        public string $token,
        public string $password,
        public string $passwordConfirmation,
    ) {
    }
}
