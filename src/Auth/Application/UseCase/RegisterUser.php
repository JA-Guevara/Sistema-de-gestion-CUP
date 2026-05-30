<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\EmailAlreadyRegistered;
use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\RegisterRequest;

/**
 * Caso de uso: registrar un nuevo usuario.
 */
final readonly class RegisterUser
{
    private const MIN_PASSWORD_LENGTH = 6;

    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @throws InvalidRegistrationData
     * @throws EmailAlreadyRegistered
     */
    public function execute(RegisterRequest $input): User
    {
        $email = mb_strtolower(trim($input->email));
        $firstName = trim($input->firstName);
        $lastName = trim($input->lastName);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidRegistrationData('El correo no es válido.');
        }

        if ($firstName === '') {
            throw new InvalidRegistrationData('El nombre es obligatorio.');
        }

        if ($lastName === '') {
            throw new InvalidRegistrationData('El apellido es obligatorio.');
        }

        if (strlen($input->password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidRegistrationData(
                sprintf('La contraseña debe tener al menos %d caracteres.', self::MIN_PASSWORD_LENGTH)
            );
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new EmailAlreadyRegistered('Ya existe un usuario con ese correo.');
        }

        $user = new User();
        $user->email = $email;
        $user->firstName = $firstName;
        $user->lastName = $lastName;
        $user->passwordHash = password_hash($input->password, PASSWORD_DEFAULT);

        $this->users->save($user);

        return $user;
    }
}
