<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Usuario\Domain\Exception\UsuarioException;

final readonly class ShowUser
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(int $id): User
    {
        return $this->loadUser($id);
    }

    private function loadUser(int $id): User
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new UsuarioException('El usuario solicitado no existe.');
        }

        return $user;
    }
}
