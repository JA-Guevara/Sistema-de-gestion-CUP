<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Usuario\Application\DTO\RoleInput;
use App\Usuario\Domain\Entity\Permission;
use App\Usuario\Domain\Entity\Role;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\Infrastructure\Persistence\PermissionRepository;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class CreateRole
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(RoleInput $input): Role
    {
        $permissions = $this->loadPermissions($input);
        $this->validateRequiredData($input);
        $this->validateUniqueName($input);
        $this->validatePermissions($input, $permissions);
        $role = $this->createRole($input, $permissions);
        $this->saveRole($role);
        $this->registerAudit($role, $input);
        $this->notify($role);

        return $role;
    }

    /** @return list<Permission> */
    private function loadPermissions(RoleInput $input): array
    {
        return $this->permissions->findByIds($input->permissionIds);
    }

    private function validateRequiredData(RoleInput $input): void
    {
        if (trim($input->name) === '') {
            throw new UsuarioException('El nombre del rol es obligatorio.');
        }
    }

    private function validateUniqueName(RoleInput $input): void
    {
        if ($this->roles->findByName($input->name) !== null) {
            throw new UsuarioException('Ya existe un rol con ese nombre.');
        }
    }

    /** @param list<Permission> $permissions */
    private function validatePermissions(RoleInput $input, array $permissions): void
    {
        if ($input->permissionIds !== [] && count($permissions) !== count(array_unique($input->permissionIds))) {
            throw new UsuarioException('Uno o mas permisos seleccionados no son validos.');
        }
    }

    /** @param list<Permission> $permissions */
    private function createRole(RoleInput $input, array $permissions): Role
    {
        $role = new Role();
        $role->configure($input->name, $input->description, $input->active);
        $role->syncPermissions($permissions);

        return $role;
    }

    private function saveRole(Role $role): void
    {
        $this->roles->save($role);
    }

    private function registerAudit(Role $role, RoleInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::ROLES, sprintf('Se creo el rol %s.', $role->name), $input->actorUserId);
    }

    private function notify(Role $role): void
    {
        // La gestion de roles no requiere notificacion automatica.
    }
}
