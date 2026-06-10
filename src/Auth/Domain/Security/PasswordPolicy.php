<?php

declare(strict_types=1);

namespace App\Auth\Domain\Security;

use App\Auth\Domain\Exception\InvalidRegistrationData;

final readonly class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public function validate(string $password): void
    {
        if (trim($password) === '') {
            throw new InvalidRegistrationData('La contrasena es obligatoria.');
        }

        if (strlen($password) < self::MIN_LENGTH) {
            throw new InvalidRegistrationData(sprintf('La contrasena debe tener al menos %d caracteres.', self::MIN_LENGTH));
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new InvalidRegistrationData('La contrasena debe incluir al menos una letra minuscula.');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new InvalidRegistrationData('La contrasena debe incluir al menos una letra mayuscula.');
        }

        if (!preg_match('/\d/', $password)) {
            throw new InvalidRegistrationData('La contrasena debe incluir al menos un numero.');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new InvalidRegistrationData('La contrasena debe incluir al menos un simbolo.');
        }
    }
}
