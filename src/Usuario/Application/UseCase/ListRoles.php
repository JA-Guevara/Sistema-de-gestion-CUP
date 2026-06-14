<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Usuario\Domain\Entity\Role;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class ListRoles
{
    public function __construct(private RoleRepository $roles)
    {
    }

    /** @return list<Role> */
    public function execute(): array
    {
        return $this->loadRoles();
    }

    /** @return list<Role> */
    private function loadRoles(): array
    {
        return $this->roles->listAll();
    }
}
