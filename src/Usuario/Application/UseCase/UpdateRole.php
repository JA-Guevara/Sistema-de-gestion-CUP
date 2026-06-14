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

final readonly class UpdateRole
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(int $id, RoleInput $input): Role
    {
        $role = $this->loadRole($id);
        $permissions = $this->loadPermissions($input);
        $this->validateRequiredData($input);
        $this->validateUniqueName($input, $role);
        $this->validatePermissions($input, $permissions);
        $this->updateRole($role, $input, $permissions);
        $this->saveRole($role);
        $this->registerAudit($role, $input);
        $this->notify($role);

        return $role;
    }

    private function loadRole(int $id): Role
    {
        $role = $this->roles->findById($id);
        if ($role === null) {
            throw new UsuarioException('El rol solicitado no existe.');
        }

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

    private function validateUniqueName(RoleInput $input, Role $role): void
    {
        $existing = $this->roles->findByName($input->name);
        if ($existing !== null && $existing->id !== $role->id) {
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
    private function updateRole(Role $role, RoleInput $input, array $permissions): void
    {
        $role->configure($input->name, $input->description, $input->active);
        $role->syncPermissions($permissions);
    }

    private function saveRole(Role $role): void
    {
        $this->roles->save($role);
    }

    private function registerAudit(Role $role, RoleInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::ROLES, sprintf('Se actualizo el rol %s.', $role->name), $input->actorUserId);
    }

    private function notify(Role $role): void
    {
        // La gestion de roles no requiere notificacion automatica.
    }
}
