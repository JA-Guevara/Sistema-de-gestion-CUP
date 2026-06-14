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

final readonly class CreateUser
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private PasswordPolicy $passwordPolicy,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(UsuarioInput $input): User
    {
        $roles = $this->loadRoles($input);
        $this->validateRequiredData($input);
        $this->validateEmail($input);
        $this->validatePassword($input);
        $this->validateUniqueEmail($input);
        $user = $this->createUser($input, $roles);
        $this->saveUser($user);
        $this->registerAudit($user, $input);
        $this->notify($user);

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

    private function validatePassword(UsuarioInput $input): void
    {
        try {
            $this->passwordPolicy->validate((string) $input->password);
        } catch (InvalidRegistrationData $exception) {
            throw new UsuarioException($exception->getMessage());
        }
    }

    private function validateUniqueEmail(UsuarioInput $input): void
    {
        if ($this->users->findByEmail($input->email) !== null) {
            throw new UsuarioException('Ya existe un usuario con ese correo.');
        }
    }

    /** @param list<Role> $roles */
    private function createUser(UsuarioInput $input, array $roles): User
    {
        $user = new User();
        $user->firstName = trim($input->firstName);
        $user->lastName = trim($input->lastName);
        $user->email = mb_strtolower(trim($input->email));
        $user->passwordHash = password_hash((string) $input->password, PASSWORD_DEFAULT);
        $user->active = $input->active;
        $user->syncRoles($roles);

        return $user;
    }

    private function saveUser(User $user): void
    {
        $this->users->save($user);
    }

    private function registerAudit(User $user, UsuarioInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::USUARIOS, sprintf('Se creo el usuario %s.', $user->email), $input->actorUserId);
    }

    private function notify(User $user): void
    {
        // Sin notificacion por correo en la creacion administrativa.
    }
}
