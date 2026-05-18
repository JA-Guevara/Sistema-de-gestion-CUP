<?php

declare(strict_types=1);

namespace App\Auth\UI\Request;

/**
 * Datos crudos del formulario de registro.
 */
final readonly class RegisterRequest
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
    ) {
    }
}
