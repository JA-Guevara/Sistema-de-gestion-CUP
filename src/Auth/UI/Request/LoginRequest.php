<?php

declare(strict_types=1);

namespace App\Auth\UI\Request;

/**
 * Datos crudos del formulario de login.
 *
 * Transportador inmutable que cruza desde el controller (capa UI)
 * hasta el caso de uso (capa Application).
 */
final readonly class LoginRequest
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
