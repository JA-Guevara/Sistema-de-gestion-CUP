<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Entity\PasswordResetToken;
use App\Auth\Infrastructure\Mailer\PasswordResetMailer;
use App\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\ForgotPasswordRequest;

/**
 * Caso de uso: el usuario solicita reset de contraseña.
 *
 * - Si el email no existe, NO falla (defensa contra enumeración de usuarios).
 * - Si existe: invalida cualquier token anterior, genera uno nuevo con
 *   expiración a 30 min, lo guarda y dispara el email.
 */
final readonly class ForgotPassword
{
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private PasswordResetMailer $mailer,
    ) {
    }

    public function execute(ForgotPasswordRequest $input): void
    {
        $email = mb_strtolower(trim($input->email));
        $user = $this->users->findByEmail($email);

        // Defensa anti-enumeración: respondemos lo mismo aunque el email no exista.
        if ($user === null) {
            return;
        }

        // Invalida tokens viejos no usados de este usuario.
        $this->tokens->markAllUnusedAsUsedFor($user->id);

        // Genera y guarda el nuevo token.
        $token = new PasswordResetToken();
        $token->userId = $user->id;
        $token->token = bin2hex(random_bytes(32));
        $token->expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', self::TOKEN_TTL_MINUTES));

        $this->tokens->save($token);

        // Envía el email con el link de reset.
        $this->mailer->send($user, $token->token);
    }
}
