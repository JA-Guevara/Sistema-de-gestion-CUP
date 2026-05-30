<?php

declare(strict_types=1);

namespace App\Bitacora\Application\EventLog;

use App\Auth\Entity\User;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

/**
 * Helpers semánticos para los eventos del módulo Auth.
 *
 * Vez de llamar a RecordLogEntry con strings sueltos, los controllers
 * y use cases de Auth llaman a estos métodos. Centralizamos acá el
 * "qué decir" para cada tipo de evento, y los callers quedan limpios.
 */
final readonly class AuthEvents
{
    public function __construct(private RecordLogEntry $recorder)
    {
    }

    public function loginExitoso(User $user): void
    {
        $this->recorder->execute(
            action: ActionCatalog::LOGIN,
            module: ModuleCatalog::AUTH,
            description: sprintf('%s inició sesión.', $user->email),
            userId: $user->id,
            userLabel: $user->email,
        );
    }

    public function loginFallido(string $email): void
    {
        $this->recorder->execute(
            action: ActionCatalog::LOGIN_FAILED,
            module: ModuleCatalog::AUTH,
            description: sprintf('Intento fallido de inicio de sesión con %s.', $email !== '' ? $email : '(sin email)'),
            userLabel: $email !== '' ? $email : '(anónimo)',
        );
    }

    public function logout(User $user): void
    {
        $this->recorder->execute(
            action: ActionCatalog::LOGOUT,
            module: ModuleCatalog::AUTH,
            description: sprintf('%s cerró sesión.', $user->email),
            userId: $user->id,
            userLabel: $user->email,
        );
    }

    public function registroExitoso(User $user): void
    {
        $this->recorder->execute(
            action: ActionCatalog::REGISTER,
            module: ModuleCatalog::AUTH,
            description: sprintf('Nuevo registro: %s %s (%s).', $user->firstName, $user->lastName, $user->email),
            userId: $user->id,
            userLabel: $user->email,
        );
    }

    public function passwordResetSolicitado(string $email): void
    {
        $this->recorder->execute(
            action: ActionCatalog::PASSWORD_RESET_REQUESTED,
            module: ModuleCatalog::AUTH,
            description: sprintf('Se solicitó recuperación de contraseña para %s.', $email),
            userLabel: $email,
        );
    }

    public function passwordResetCompletado(User $user): void
    {
        $this->recorder->execute(
            action: ActionCatalog::PASSWORD_RESET_COMPLETED,
            module: ModuleCatalog::AUTH,
            description: sprintf('%s actualizó su contraseña via enlace de recuperación.', $user->email),
            userId: $user->id,
            userLabel: $user->email,
        );
    }

    public function cuentaBloqueada(User $user): void
    {
        $this->recorder->execute(
            action: ActionCatalog::ACCOUNT_LOCKED,
            module: ModuleCatalog::AUTH,
            description: sprintf('La cuenta de %s fue bloqueada por 3 intentos fallidos.', $user->email),
            userId: $user->id,
            userLabel: $user->email,
        );
    }

    public function cuentaDesbloqueada(User $user): void
    {
        $this->recorder->execute(
            action: ActionCatalog::ACCOUNT_UNLOCKED,
            module: ModuleCatalog::AUTH,
            description: sprintf('%s desbloqueó su cuenta con el código enviado por email.', $user->email),
            userId: $user->id,
            userLabel: $user->email,
        );
    }
}
