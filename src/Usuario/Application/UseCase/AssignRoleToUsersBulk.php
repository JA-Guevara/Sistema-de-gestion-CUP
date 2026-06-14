<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Usuario\Application\DTO\UsuariosRolBulkInput;
use App\Usuario\Domain\Entity\Role;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class AssignRoleToUsersBulk
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(UsuariosRolBulkInput $input): int
    {
        $role = $this->loadRole($input);
        $users = $this->loadUsers($input);
        $this->validateRole($role);
        $this->validateUsers($users);
        $updatedUsers = $this->updateUsers($users, $role);
        $this->saveUsers($updatedUsers);
        $this->registerAudit($role, $updatedUsers, $input);
        $this->notify($role, $updatedUsers);

        return count($updatedUsers);
    }

    private function loadRole(UsuariosRolBulkInput $input): Role
    {
        $role = $this->roles->findById($input->roleId);
        if ($role === null) {
            throw new UsuarioException('El rol seleccionado no existe.');
        }

        return $role;
    }

    /** @return list<User> */
    private function loadUsers(UsuariosRolBulkInput $input): array
    {
        return $this->users->findByIds($input->userIds);
    }

    private function validateRole(Role $role): void
    {
        if (!$role->active) {
            throw new UsuarioException('No puedes asignar un rol inactivo.');
        }
    }

    /** @param list<User> $users */
    private function validateUsers(array $users): void
    {
        if ($users === []) {
            throw new UsuarioException('Selecciona al menos un usuario.');
        }
    }

    /** @param list<User> $users @return list<User> */
    private function updateUsers(array $users, Role $role): array
    {
        $updated = [];
        foreach ($users as $user) {
            if (!$user->active || $user->hasRole($role->name)) {
                continue;
            }

            $user->syncRoles(array_merge($user->roles(), [$role]));
            $updated[] = $user;
        }

        return $updated;
    }

    /** @param list<User> $users */
    private function saveUsers(array $users): void
    {
        if ($users !== []) {
            $this->users->saveMany($users);
        }
    }

    /** @param list<User> $users */
    private function registerAudit(Role $role, array $users, UsuariosRolBulkInput $input): void
    {
        $this->audit->execute(
            ActionCatalog::ASSIGN,
            ModuleCatalog::USUARIOS,
            sprintf('Se asigno el rol %s a %d usuario(s).', $role->name, count($users)),
            $input->actorUserId,
        );
    }

    /** @param list<User> $users */
    private function notify(Role $role, array $users): void
    {
        // La asignacion masiva queda registrada en bitacora; no envia correos.
    }
}
