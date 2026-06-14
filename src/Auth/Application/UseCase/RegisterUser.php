<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\EmailAlreadyRegistered;
use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Domain\Security\PasswordPolicy;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\RegisterRequest;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

/**
 * Caso de uso: registrar un nuevo usuario.
 */
final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordPolicy $passwordPolicy,
        private RoleRepository $roles,
    ) {
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
            throw new InvalidRegistrationData('El correo no es valido.');
        }

        if ($firstName === '') {
            throw new InvalidRegistrationData('El nombre es obligatorio.');
        }

        if ($lastName === '') {
            throw new InvalidRegistrationData('El apellido es obligatorio.');
        }

        $this->passwordPolicy->validate($input->password);

        if ($this->users->findByEmail($email) !== null) {
            throw new EmailAlreadyRegistered('Ya existe un usuario con ese correo.');
        }

        $user = new User();
        $user->email = $email;
        $user->firstName = $firstName;
        $user->lastName = $lastName;
        $user->passwordHash = password_hash($input->password, PASSWORD_DEFAULT);

        // Todo auto-registro entra como Postulante (rol base de acceso a inscripción/perfil).
        $postulante = $this->roles->findByName('Postulante');
        if ($postulante !== null) {
            $user->syncRoles([$postulante]);
        }

        $this->users->save($user);

        return $user;
    }
}
