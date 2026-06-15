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
 * - Al MAX_FAILED_ATTEMPTS bloquea la cuenta, genera codigo y envia email.
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

        if ($user === null) {
            throw new InvalidCredentials('Credenciales invalidas.');
        }

        if ($user->locked) {
            throw new AccountLocked('Tu cuenta esta bloqueada. Revisa tu correo o desbloquea desde el enlace.');
        }

        if (!$user->active) {
            throw new InvalidCredentials('Tu cuenta esta inactiva. Comunicate con administracion.');
        }

        if (!password_verify($input->password, $user->passwordHash)) {
            $this->registerFailedAttempt($user);
        }

        $this->resetFailedAttempts($user);

        return $user;
    }

    /**
     * @throws InvalidCredentials
     * @throws AccountLocked
     */
    private function registerFailedAttempt(User $user): void
    {
        $user->failedLoginAttempts++;

        if ($user->failedLoginAttempts < self::MAX_FAILED_ATTEMPTS) {
            $this->users->save($user);

            throw new InvalidCredentials('Credenciales invalidas.');
        }

        $mailSent = $this->lockAccount($user);
        $message = $mailSent
            ? 'Tu cuenta fue bloqueada por demasiados intentos fallidos. Te enviamos un codigo de desbloqueo por correo.'
            : 'Tu cuenta fue bloqueada por demasiados intentos fallidos. No pudimos enviar el codigo automaticamente; usa Reenviar codigo.';

        throw new AccountLocked($message);
    }

    private function resetFailedAttempts(User $user): void
    {
        if ($user->failedLoginAttempts <= 0) {
            return;
        }

        $user->failedLoginAttempts = 0;
        $this->users->save($user);
    }

    private function lockAccount(User $user): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));

        $user->locked = true;
        $user->unlockCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->unlockCodeExpiresAt = $now->modify(sprintf('+%d minutes', self::UNLOCK_CODE_TTL_MINUTES));
        $user->unlockCodeLastSentAt = null;

        $this->users->save($user);

        try {
            $this->lockedMailer->send($user, $user->unlockCode);
        } catch (\Throwable $exception) {
            error_log(sprintf(
                '[CUP][auth] No se pudo enviar el codigo de desbloqueo (user %d): %s',
                $user->id,
                $exception->getMessage(),
            ));

            return false;
        }

        $user->unlockCodeLastSentAt = $now;
        $this->users->save($user);

        return true;
    }
}
