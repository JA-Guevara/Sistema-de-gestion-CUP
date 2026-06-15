<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Prueba el envío de correo por SMTP (Gmail gratuito).
 * Uso: php bin/console app:mail:test [destinatario]
 */
#[AsCommand(
    name: 'app:mail:test',
    description: 'Envía un correo de prueba y muestra si el SMTP lo aceptó.',
)]
final class TestMailCommand extends Command
{
    public function __construct(private readonly MailerInterface $mailer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::OPTIONAL, 'Correo destinatario (por defecto, el MAIL_FROM)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = (string) ($_ENV['MAIL_USER'] ?? getenv('MAIL_USER') ?: '');
        $pass = (string) ($_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: '');
        $from = (string) ($_ENV['MAIL_FROM'] ?? getenv('MAIL_FROM') ?: 'no-reply@cup-ficct.local');
        $fromName = (string) ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'CUP FICCT');
        $to = (string) ($input->getArgument('to') ?: ($from ?: $user));

        $io->section('Configuración efectiva');
        $io->writeln('  Host SMTP : smtp.gmail.com:587');
        $io->writeln('  MAIL_USER : '.$this->mask($user));
        $io->writeln('  Password  : '.($pass === '' ? '(VACÍO — falta la App Password)' : strlen($pass).' caracteres'));
        $io->writeln('  MAIL_FROM : '.$from);
        $io->writeln('  Destino   : '.$to);
        $io->newLine();

        if ($to === '') {
            $io->error('No hay destinatario. Usá: php bin/console app:mail:test alguien@correo.com');

            return Command::FAILURE;
        }

        $email = (new Email())
            ->from(new Address($from, $fromName))
            ->to($to)
            ->subject('Prueba de envío - CUP FICCT')
            ->text("Correo de prueba del sistema CUP FICCT.\nSi te llegó, el envío funciona.")
            ->html('<p>Correo de <strong>prueba</strong> del sistema CUP FICCT.</p><p>Si te llegó, el envío funciona ✅</p>');

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $io->error('FALLÓ el envío (el SMTP rechazó el correo).');
            $io->writeln('  Tipo   : '.$e::class);
            $io->writeln('  Motivo : '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success('El SMTP aceptó el correo. Revisá la bandeja de '.$to.' (mirá también spam).');

        return Command::SUCCESS;
    }

    /** Oculta el correo dejando solo una pista. */
    private function mask(string $value): string
    {
        if ($value === '') {
            return '(vacío)';
        }

        $at = strpos($value, '@');
        if ($at === false || $at <= 1) {
            return substr($value, 0, 1).'***';
        }

        return substr($value, 0, 2).'***'.substr($value, $at);
    }
}
