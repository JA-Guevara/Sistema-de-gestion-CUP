<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Domain\Security\PasswordPolicy;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Usuario\Application\DTO\UsuarioInput;
use App\Usuario\Domain\Entity\Role;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class UpdateUser
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private PasswordPolicy $passwordPolicy,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(int $id, UsuarioInput $input): User
    {
        $user = $this->loadUser($id);
        $roles = $this->loadRoles($input);
        $this->validateRequiredData($input);
        $this->validateEmail($input);
        $this->validatePasswordIfPresent($input, $user);
        $this->validateUniqueEmail($input, $user);
        $this->updateUser($user, $input, $roles);
        $this->saveUser($user);
        $this->registerAudit($user, $input);
        $this->notify($user);

        return $user;
    }

    private function loadUser(int $id): User
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new UsuarioException('El usuario solicitado no existe.');
        }

        return $user;
    }

    /** @return list<Role> */
    private function loadRoles(UsuarioInput $input): array
    {
        return $this->roles->findByIds($input->roleIds);
    }

    private function validateRequiredData(UsuarioInput $input): void
    {
        if (trim($input->firstName) === '' || trim($input->lastName) === '') {
            throw new UsuarioException('Nombre y apellido son obligatorios.');
        }
    }

    private function validateEmail(UsuarioInput $input): void
    {
        if (!filter_var(mb_strtolower(trim($input->email)), FILTER_VALIDATE_EMAIL)) {
            throw new UsuarioException('El correo no es valido.');
        }
    }

    private function validatePasswordIfPresent(UsuarioInput $input, User $user): void
    {
        if ($input->password === null || trim($input->password) === '') {
            return;
        }

        try {
            $this->passwordPolicy->validate($input->password);
        } catch (InvalidRegistrationData $exception) {
            throw new UsuarioException($exception->getMessage());
        }

        if (password_verify($input->password, $user->passwordHash)) {
            throw new UsuarioException('La nueva contrasena no puede ser igual a la anterior.');
        }
    }

    private function validateUniqueEmail(UsuarioInput $input, User $user): void
    {
        $existing = $this->users->findByEmail($input->email);
        if ($existing !== null && $existing->id !== $user->id) {
            throw new UsuarioException('Ya existe un usuario con ese correo.');
        }
    }

    /** @param list<Role> $roles */
    private function updateUser(User $user, UsuarioInput $input, array $roles): void
    {
        $user->firstName = trim($input->firstName);
        $user->lastName = trim($input->lastName);
        $user->email = mb_strtolower(trim($input->email));
        $user->active = $input->active;
        $user->syncRoles($roles);

        if ($input->password !== null && trim($input->password) !== '') {
            $user->passwordHash = password_hash($input->password, PASSWORD_DEFAULT);
            $user->currentSessionId = null;
        }
    }

    private function saveUser(User $user): void
    {
        $this->users->save($user);
    }

    private function registerAudit(User $user, UsuarioInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::USUARIOS, sprintf('Se actualizo el usuario %s.', $user->email), $input->actorUserId);
    }

    private function notify(User $user): void
    {
        // Sin notificacion automatica al editar datos administrativos.
    }
}
