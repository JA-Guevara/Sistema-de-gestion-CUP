<?php

declare(strict_types=1);

namespace App\Auth\UI\Request;

final readonly class ForgotPasswordRequest
{
    public function __construct(
        public string $email,
    ) {
    }
}
