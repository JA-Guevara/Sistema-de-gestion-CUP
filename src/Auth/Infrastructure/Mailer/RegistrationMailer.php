<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Mailer;

use App\Auth\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envia el correo de bienvenida cuando un postulante crea su cuenta.
 */
final readonly class RegistrationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function send(User $user): void
    {
        $loginUrl = $this->urls->generate('auth_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(SenderAddress::resolve())
            ->to(new Address($user->email, $user->firstName.' '.$user->lastName))
            ->subject('Bienvenido al CUP FICCT - Cuenta creada')
            ->htmlTemplate('@auth/email/welcome.html.twig')
            ->context([
                'user' => $user,
                'login_url' => $loginUrl,
            ]);

        $this->mailer->send($email);
    }
}
