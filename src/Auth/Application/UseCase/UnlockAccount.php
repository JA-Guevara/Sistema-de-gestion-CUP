<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\UnlockCodeExpired;
use App\Auth\Domain\Exception\UnlockCodeInvalid;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\UnlockAccountRequest;

/**
 * Caso de uso: desbloquear una cuenta usando el código enviado por email.
 *
 * Valida email + código, chequea expiración, y si todo OK resetea el estado
 * de bloqueo del User.
 */
final readonly class UnlockAccount
{
    public function __construct(private UserRepository $users)
    {
    }

    /**
     * @throws UnlockCodeInvalid
     * @throws UnlockCodeExpired
     */
    public function execute(UnlockAccountRequest $input): void
    {
        $email = mb_strtolower(trim($input->email));
        $code = trim($input->code);

        $user = $this->users->findByEmail($email);

        // Mensaje genérico: no revelamos si la cuenta existe o si simplemente no estaba bloqueada.
        if ($user === null || !$user->locked || $user->unlockCode === null) {
            throw new UnlockCodeInvalid('Datos no válidos.');
        }

        if ($user->unlockCodeExpiresAt === null || $user->unlockCodeExpiresAt < new \DateTimeImmutable()) {
            throw new UnlockCodeExpired('El código expiró. Solicitá uno nuevo.');
        }

        if (!hash_equals($user->unlockCode, $code)) {
            throw new UnlockCodeInvalid('Código incorrecto.');
        }

        // Desbloquear: limpiar todo el estado de bloqueo.
        $user->locked = false;
        $user->failedLoginAttempts = 0;
        $user->unlockCode = null;
        $user->unlockCodeExpiresAt = null;
        $user->unlockCodeLastSentAt = null;

        $this->users->save($user);
    }
}
