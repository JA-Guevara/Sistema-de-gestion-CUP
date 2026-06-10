<?php

declare(strict_types=1);

namespace App\Auth\Application\UseCase;

use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Domain\Exception\PasswordResetTokenExpired;
use App\Auth\Domain\Exception\PasswordResetTokenInvalid;
use App\Auth\Domain\Security\PasswordPolicy;
use App\Auth\Entity\PasswordResetToken;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\UI\Request\ResetPasswordRequest;

/**
 * Caso de uso: aplicar la nueva contrasena a partir de un token valido.
 */
final readonly class ResetPassword
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private PasswordPolicy $passwordPolicy,
    ) {
    }

    /**
     * @throws PasswordResetTokenInvalid
     * @throws PasswordResetTokenExpired
     * @throws InvalidRegistrationData
     */
    public function execute(ResetPasswordRequest $input): void
    {
        $token = $this->loadValidToken($input);
        $user = $this->loadUser($token);
        $this->validatePassword($input, $user);
        $this->updatePassword($user, $input);
        $this->saveUser($user);
        $this->consumeToken($token);
    }

    private function loadValidToken(ResetPasswordRequest $input): PasswordResetToken
    {
        $token = $this->tokens->findByToken($input->token);
        if ($token === null || $token->isUsed()) {
            throw new PasswordResetTokenInvalid('El enlace de recuperacion no es valido o ya fue usado.');
        }

        if ($token->isExpired()) {
            throw new PasswordResetTokenExpired('El enlace de recuperacion expiro. Solicita uno nuevo.');
        }

        return $token;
    }

    private function loadUser(PasswordResetToken $token): User
    {
        $user = $this->users->findById($token->userId);
        if ($user === null) {
            throw new PasswordResetTokenInvalid('El enlace de recuperacion no es valido o ya fue usado.');
        }

        return $user;
    }

    private function validatePassword(ResetPasswordRequest $input, User $user): void
    {
        $this->passwordPolicy->validate($input->password);

        if ($input->password !== $input->passwordConfirmation) {
            throw new InvalidRegistrationData('Las contrasenas no coinciden.');
        }

        if (password_verify($input->password, $user->passwordHash)) {
            throw new InvalidRegistrationData('La nueva contrasena no puede ser igual a la anterior.');
        }
    }

    private function updatePassword(User $user, ResetPasswordRequest $input): void
    {
        $user->passwordHash = password_hash($input->password, PASSWORD_DEFAULT);
        $user->currentSessionId = null;
    }

    private function saveUser(User $user): void
    {
        $this->users->save($user);
    }

    private function consumeToken(PasswordResetToken $token): void
    {
        $token->usedAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
        $this->tokens->save($token);
    }
}
