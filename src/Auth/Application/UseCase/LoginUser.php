<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\AccountLocked;
use App\Auth\Domain\Exception\InvalidCredentials;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Mailer\AccountLockedMailer;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\LoginRequest;

/**
 * Caso de uso: validar credenciales y devolver el usuario.
 *
 * Defensa contra fuerza bruta:
 * - Cuenta cada intento fallido consecutivo.
 * - Al MAX_FAILED_ATTEMPTS (3), bloquea la cuenta, genera código y envía email.
 * - Un login exitoso resetea el contador.
 */
final readonly class LoginUser
{
    private const MAX_FAILED_ATTEMPTS = 3;
    private const UNLOCK_CODE_TTL_MINUTES = 60;

    public function __construct(
        private UserRepository $users,
        private AccountLockedMailer $lockedMailer,
    ) {
    }

    /**
     * @throws InvalidCredentials
     * @throws AccountLocked
     */
    public function execute(LoginRequest $input): User
    {
        $user = $this->users->findByEmail($input->email);

        // Email no existe: error genérico (defensa anti-enumeración en primer intento).
        if ($user === null) {
            throw new InvalidCredentials('Credenciales inválidas.');
        }

        // Cuenta ya bloqueada: rechazar antes de chequear password.
        if ($user->locked) {
            throw new AccountLocked('Tu cuenta está bloqueada. Revisá tu correo o desbloqueá desde el enlace.');
        }

        // Password mal: incrementar contador y eventualmente bloquear.
        if (!password_verify($input->password, $user->passwordHash)) {
            $user->failedLoginAttempts++;

            if ($user->failedLoginAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $this->lockAccount($user);
                throw new AccountLocked('Tu cuenta fue bloqueada por demasiados intentos fallidos. Te enviamos un código de desbloqueo por correo.');
            }

            $this->users->save($user);
            throw new InvalidCredentials('Credenciales inválidas.');
        }

        // Login OK: resetear contador si venía acumulado.
        if ($user->failedLoginAttempts > 0) {
            $user->failedLoginAttempts = 0;
            $this->users->save($user);
        }

        return $user;
    }

    private function lockAccount(User $user): void
    {
        $now = new \DateTimeImmutable();

        $user->locked = true;
        $user->unlockCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->unlockCodeExpiresAt = $now->modify(sprintf('+%d minutes', self::UNLOCK_CODE_TTL_MINUTES));
        $user->unlockCodeLastSentAt = $now;

        $this->users->save($user);
        $this->lockedMailer->send($user, $user->unlockCode);
    }
}
