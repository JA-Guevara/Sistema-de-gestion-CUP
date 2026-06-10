<?php

declare(strict_types=1);

namespace App\Perfil\Application\UseCase;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\PerfilEvents;
use App\Perfil\Application\DTO\PerfilInput;
use App\Perfil\Domain\Exception\PerfilException;

/**
 * Caso de uso: actualizar los datos básicos del propio perfil.
 */
final readonly class UpdatePerfil
{
    public function __construct(
        private UserRepository $users,
        private PerfilEvents $events,
    ) {
    }

    public function execute(PerfilInput $input): User
    {
        $user = $this->users->findById($input->userId);
        if ($user === null) {
            throw new PerfilException('El usuario no existe.');
        }

        $firstName = trim($input->firstName);
        $lastName = trim($input->lastName);

        if ($firstName === '') {
            throw new PerfilException('El nombre es obligatorio.');
        }

        if ($lastName === '') {
            throw new PerfilException('El apellido es obligatorio.');
        }

        $antes = ['firstName' => $user->firstName, 'lastName' => $user->lastName];

        $user->firstName = $firstName;
        $user->lastName = $lastName;
        $this->users->save($user);

        $this->events->datosActualizados($user->id ?? 0, $user->email, $antes, ['firstName' => $firstName, 'lastName' => $lastName]);

        return $user;
    }
}
