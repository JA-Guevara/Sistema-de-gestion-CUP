<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Mailer;

use App\Auth\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envía el correo de recuperación de contraseña con el link al form de reset.
 */
final readonly class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** Remitente resuelto de forma segura para SMTP (ver SenderAddress). */
    private function from(): Address
    {
        return SenderAddress::resolve();
    }

    public function send(User $user, string $token): void
    {
        $resetUrl = $this->urls->generate(
            'auth_reset',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from($this->from())
            ->to(new Address($user->email, $user->firstName.' '.$user->lastName))
            ->subject('Recuperación de contraseña - CUP FICCT')
            ->htmlTemplate('@auth/email/password_reset.html.twig')
            ->context([
                'user' => $user,
                'reset_url' => $resetUrl,
                'expires_minutes' => 30,
            ]);

        $this->mailer->send($email);
    }
}
