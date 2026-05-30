<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\UnlockCodeRequestedTooSoon;
use App\Auth\Infrastructure\Mailer\AccountLockedMailer;
use App\Auth\Infrastructure\Persistence\UserRepository;

/**
 * Caso de uso: reenviar el código de desbloqueo.
 *
 * - Si el email no existe o la cuenta no está bloqueada, NO falla (defensa
 *   contra enumeración: el atacante no sabe si la cuenta existe o está bloqueada).
 * - Si está bloqueada y pasaron menos de COOLDOWN_MINUTES desde el último envío,
 *   lanza UnlockCodeRequestedTooSoon.
 * - Si todo OK: genera código nuevo, actualiza expiración y lo manda por email.
 */
final readonly class ResendUnlockCode
{
    private const COOLDOWN_MINUTES = 5;
    private const CODE_TTL_MINUTES = 60;

    public function __construct(
        private UserRepository $users,
        private AccountLockedMailer $mailer,
    ) {
    }

    /**
     * @throws UnlockCodeRequestedTooSoon
     */
    public function execute(string $email): void
    {
        $normalized = mb_strtolower(trim($email));
        $user = $this->users->findByEmail($normalized);

        // Anti-enumeración: respuesta silenciosa si no existe o no está bloqueada.
        if ($user === null || !$user->locked) {
            return;
        }

        $now = new \DateTimeImmutable();

        // Cooldown: si el último envío fue hace menos de 5 min, rechazar.
        if ($user->unlockCodeLastSentAt !== null) {
            $secondsSinceLastSend = $now->getTimestamp() - $user->unlockCodeLastSentAt->getTimestamp();
            $cooldownSeconds = self::COOLDOWN_MINUTES * 60;

            if ($secondsSinceLastSend < $cooldownSeconds) {
                $secondsLeft = $cooldownSeconds - $secondsSinceLastSend;
                throw new UnlockCodeRequestedTooSoon(
                    sprintf('Esperá %d segundos antes de pedir otro código.', $secondsLeft)
                );
            }
        }

        // Generar código nuevo y reenviar.
        $user->unlockCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->unlockCodeExpiresAt = $now->modify(sprintf('+%d minutes', self::CODE_TTL_MINUTES));
        $user->unlockCodeLastSentAt = $now;

        $this->users->save($user);
        $this->mailer->send($user, $user->unlockCode);
    }
}
