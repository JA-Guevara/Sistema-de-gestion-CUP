<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Usuario\Domain\Entity\Role;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class ShowRole
{
    public function __construct(private RoleRepository $roles)
    {
    }

    public function execute(int $id): Role
    {
        return $this->loadRole($id);
    }

    private function loadRole(int $id): Role
    {
        $role = $this->roles->findById($id);
        if ($role === null) {
            throw new UsuarioException('El rol solicitado no existe.');
        }

        return $role;
    }
}
