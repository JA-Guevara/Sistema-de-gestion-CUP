<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Usuario\Application\DTO\RoleActionInput;
use App\Usuario\Domain\Entity\Role;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class ToggleRoleState
{
    public function __construct(
        private RoleRepository $roles,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(RoleActionInput $input): Role
    {
        $role = $this->loadRole($input);
        $this->validateCanChangeState($role);
        $this->updateRoleState($role);
        $this->saveRole($role);
        $this->registerAudit($role, $input);
        $this->notify($role);

        return $role;
    }

    private function loadRole(RoleActionInput $input): Role
    {
        $role = $this->roles->findById($input->roleId);
        if ($role === null) {
            throw new UsuarioException('El rol solicitado no existe.');
        }

        return $role;
    }

    private function validateCanChangeState(Role $role): void
    {
        if (mb_strtolower($role->name) === 'administrador' && $role->active) {
            throw new UsuarioException('El rol Administrador no puede desactivarse.');
        }
    }

    private function updateRoleState(Role $role): void
    {
        if ($role->active) {
            $role->deactivate();
            return;
        }

        $role->activate();
    }

    private function saveRole(Role $role): void
    {
        $this->roles->save($role);
    }

    private function registerAudit(Role $role, RoleActionInput $input): void
    {
        $action = $role->active ? ActionCatalog::ACTIVATE : ActionCatalog::DEACTIVATE;
        $this->audit->execute($action, ModuleCatalog::ROLES, sprintf('Se cambio el estado del rol %s.', $role->name), $input->actorUserId);
    }

    private function notify(Role $role): void
    {
        // La gestion de roles no requiere notificacion automatica.
    }
}
