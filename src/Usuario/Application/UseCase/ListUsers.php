<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;

final readonly class ListUsers
{
    public function __construct(private UserRepository $users)
    {
    }

    /** @return list<User> */
    public function execute(): array
    {
        return $this->loadUsers();
    }

    /** @return list<User> */
    private function loadUsers(): array
    {
        return $this->users->listAll();
    }
}
