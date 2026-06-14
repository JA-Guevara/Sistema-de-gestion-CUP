<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Usuario\Domain\Entity\Permission;
use App\Usuario\Infrastructure\Persistence\PermissionRepository;

final readonly class ListPermissions
{
    public function __construct(private PermissionRepository $permissions)
    {
    }

    /** @return array<string,list<Permission>> */
    public function execute(): array
    {
        return $this->loadPermissions();
    }

    /** @return array<string,list<Permission>> */
    private function loadPermissions(): array
    {
        return $this->permissions->listGroupedByModule();
    }
}
