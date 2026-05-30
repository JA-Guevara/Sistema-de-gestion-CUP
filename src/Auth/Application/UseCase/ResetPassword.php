<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Domain\Exception\PasswordResetTokenExpired;
use App\Auth\Domain\Exception\PasswordResetTokenInvalid;
use App\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\ResetPasswordRequest;

/**
 * Caso de uso: aplicar la nueva contraseña a partir de un token válido.
 *
 * - Valida que el token exista, no haya expirado y no haya sido usado.
 * - Valida la longitud mínima de la nueva contraseña y la confirmación.
 * - Hashea la nueva contraseña, marca el token como usado.
 * - Invalida cualquier sesión activa del usuario (currentSessionId = null)
 *   por seguridad: cualquier sesión vieja deja de coincidir y queda fuera.
 */
final readonly class ResetPassword
{
    private const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
    ) {
    }

    /**
     * @throws PasswordResetTokenInvalid
     * @throws PasswordResetTokenExpired
     * @throws InvalidRegistrationData
     */
    public function execute(ResetPasswordRequest $input): void
    {
        $token = $this->tokens->findByToken($input->token);

        if ($token === null || $token->isUsed()) {
            throw new PasswordResetTokenInvalid('El enlace de recuperación no es válido o ya fue usado.');
        }

        if ($token->isExpired()) {
            throw new PasswordResetTokenExpired('El enlace de recuperación expiró. Solicitá uno nuevo.');
        }

        if (strlen($input->password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidRegistrationData(
                sprintf('La contraseña debe tener al menos %d caracteres.', self::MIN_PASSWORD_LENGTH)
            );
        }

        if ($input->password !== $input->passwordConfirmation) {
            throw new InvalidRegistrationData('Las contraseñas no coinciden.');
        }

        $user = $this->users->findById($token->userId);

        // El user fue eliminado entre que pidió el reset y aplicó el cambio.
        if ($user === null) {
            throw new PasswordResetTokenInvalid('El enlace de recuperación no es válido o ya fue usado.');
        }

        // Actualizar contraseña.
        $user->passwordHash = password_hash($input->password, PASSWORD_DEFAULT);

        // Invalidar cualquier sesión activa: el siguiente request que use la
        // sesión vieja chocará con SessionGuard y será expulsado.
        $user->currentSessionId = null;

        $this->users->save($user);

        // Marcar token como consumido (uso único).
        $token->usedAt = new \DateTimeImmutable();
        $this->tokens->save($token);
    }
}
