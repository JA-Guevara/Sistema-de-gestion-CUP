<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Mailer;

use App\Auth\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envía el email con el código de desbloqueo cuando una cuenta queda bloqueada
 * o cuando el usuario solicita reenviar el código.
 */
final readonly class AccountLockedMailer
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

    public function send(User $user, string $code): void
    {
        $unlockUrl = $this->urls->generate(
            'auth_unlock',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from($this->from())
            ->to(new Address($user->email, $user->firstName.' '.$user->lastName))
            ->subject('Cuenta bloqueada - Código de desbloqueo - CUP FICCT')
            ->htmlTemplate('@auth/email/account_locked.html.twig')
            ->context([
                'user' => $user,
                'code' => $code,
                'unlock_url' => $unlockUrl,
                'expires_minutes' => 60,
            ]);

        $this->mailer->send($email);
    }
}
