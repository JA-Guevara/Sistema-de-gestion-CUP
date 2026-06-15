<?php

declare(strict_types=1);

namespace App\Usuario\Application\UseCase;

use App\Auth\Entity\PasswordResetToken;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Mailer\PasswordResetMailer;
use App\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Usuario\Application\DTO\UsuarioActionInput;
use App\Usuario\Domain\Exception\UsuarioException;
use Psr\Log\LoggerInterface;

final readonly class ResetUserPassword
{
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private PasswordResetMailer $mailer,
        private RecordLogEntry $audit,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(UsuarioActionInput $input): void
    {
        $user = $this->loadUser($input);
        $this->validateCanReset($user);
        $token = $this->createResetToken($user);
        $this->saveToken($token, $user);
        $this->registerAudit($user, $input);
        $this->notify($user, $token);
    }

    private function loadUser(UsuarioActionInput $input): User
    {
        $user = $this->users->findById($input->userId);
        if ($user === null) {
            throw new UsuarioException('El usuario solicitado no existe.');
        }

        return $user;
    }

    private function validateCanReset(User $user): void
    {
        if (!$user->active) {
            throw new UsuarioException('No se puede enviar reset a un usuario inactivo.');
        }
    }

    private function createResetToken(User $user): PasswordResetToken
    {
        $token = new PasswordResetToken();
        $token->userId = (int) $user->id;
        $token->token = bin2hex(random_bytes(32));
        $token->expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz')))->modify(sprintf('+%d minutes', self::TOKEN_TTL_MINUTES));

        return $token;
    }

    private function saveToken(PasswordResetToken $token, User $user): void
    {
        $this->tokens->markAllUnusedAsUsedFor((int) $user->id);
        $this->tokens->save($token);
    }

    private function registerAudit(User $user, UsuarioActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::PASSWORD_RESET_REQUESTED, ModuleCatalog::USUARIOS, sprintf('Se envio reset de contrasena al usuario %s.', $user->email), $input->actorUserId);
    }

    private function notify(User $user, PasswordResetToken $token): void
    {
        try {
            $this->mailer->send($user, $token->token);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('[CUP][usuario] No se pudo enviar el reset al usuario %s: %s', $user->email, $e->getMessage()));

            throw new UsuarioException('Se genero el reset, pero el correo no pudo enviarse. Revisa la configuracion de correo (o reenvia el enlace).');
        }
    }
}
