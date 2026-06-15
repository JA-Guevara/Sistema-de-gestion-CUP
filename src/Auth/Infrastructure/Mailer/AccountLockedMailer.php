<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Mailer;

use App\Auth\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AccountLockedMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function send(User $user, string $code, bool $isTest = false): void
    {
        $unlockUrl = $this->urls->generate('auth_unlock', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from($this->from())
            ->to(new Address($user->email, $user->firstName . ' ' . $user->lastName))
            ->subject($isTest ? '[Prueba] Codigo de desbloqueo - CUP FICCT' : 'Cuenta bloqueada - Codigo de desbloqueo - CUP FICCT')
            ->htmlTemplate('@auth/email/account_locked.html.twig')
            ->context([
                'user' => $user,
                'code' => $code,
                'unlock_url' => $unlockUrl,
                'expires_minutes' => 60,
                'is_test' => $isTest,
            ]);

        $this->mailer->send($email);
    }

    private function from(): Address
    {
        return SenderAddress::resolve();
    }
}
