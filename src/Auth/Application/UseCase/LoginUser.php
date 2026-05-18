<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\InvalidCredentials;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\LoginRequest;

/**
 * Caso de uso:
 * validar credenciales y devolver usuario.
 */
final readonly class LoginUser
{
    public function __construct(
        private UserRepository $users
    ) {
    }

    /**
     * @throws InvalidCredentials
     */
    public function execute(LoginRequest $input): User
    {
        $user = $this->users->findByEmail($input->email);

        if ($user === null) {
            throw new InvalidCredentials(
                'Credenciales inválidas.'
            );
        }

        if (
            !password_verify(
                $input->password,
                $user->passwordHash
            )
        ) {
            throw new InvalidCredentials(
                'Credenciales inválidas.'
            );
        }

        return $user;
    }
}