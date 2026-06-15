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

    /** Remitente configurable por MAIL_FROM / MAIL_FROM_NAME (.env.local). */
    private function from(): Address
    {
        $address = (string) ($_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: 'noreply@cup-ficct.local');
        $name = (string) ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'CUP FICCT');

        return new Address($address, $name);
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
