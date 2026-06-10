<?php

declare(strict_types=1);

namespace App\Perfil\Application\UseCase;

use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Domain\Security\PasswordPolicy;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\PerfilEvents;
use App\Perfil\Application\DTO\PasswordChangeInput;
use App\Perfil\Domain\Exception\PerfilException;

/**
 * Caso de uso: cambiar la contraseña del propio usuario autenticado.
 *
 * Verifica la contraseña actual, aplica la política de contraseñas y confirma
 * la coincidencia antes de guardar el nuevo hash.
 */
final readonly class ChangePassword
{
    public function __construct(
        private UserRepository $users,
        private PasswordPolicy $passwordPolicy,
        private PerfilEvents $events,
    ) {
    }

    public function execute(PasswordChangeInput $input): void
    {
        $user = $this->users->findById($input->userId);
        if ($user === null) {
            throw new PerfilException('El usuario no existe.');
        }

        if (!password_verify($input->currentPassword, $user->passwordHash)) {
            throw new PerfilException('La contraseña actual no es correcta.');
        }

        if ($input->newPassword !== $input->confirmPassword) {
            throw new PerfilException('La confirmación no coincide con la nueva contraseña.');
        }

        if (password_verify($input->newPassword, $user->passwordHash)) {
            throw new PerfilException('La nueva contraseña no puede ser igual a la actual.');
        }

        try {
            $this->passwordPolicy->validate($input->newPassword);
        } catch (InvalidRegistrationData $e) {
            throw new PerfilException($e->getMessage());
        }

        $user->passwordHash = password_hash($input->newPassword, PASSWORD_DEFAULT);
        $this->users->save($user);

        $this->events->passwordCambiado($user->id ?? 0, $user->email);
    }
}
