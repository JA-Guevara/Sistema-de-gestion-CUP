<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Console;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Mailer\AccountLockedMailer;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mail:test-unlock',
    description: 'Envia un correo de prueba usando la misma plantilla de desbloqueo.',
)]
final class TestUnlockMailCommand extends Command
{
    public function __construct(
        private readonly AccountLockedMailer $mailer,
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Correo destinatario');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = mb_strtolower(trim((string) $input->getArgument('to')));

        if ($to === '') {
            $io->error('Debes indicar un correo destino.');

            return Command::FAILURE;
        }

        $user = $this->users->findByEmail($to) ?? $this->fakeUser($to);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->mailer->send($user, $code, true);
        } catch (\Throwable $exception) {
            $io->error('Fallo el correo de desbloqueo.');
            $io->writeln('  Tipo   : ' . $exception::class);
            $io->writeln('  Motivo : ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Correo de desbloqueo aceptado por SMTP para %s. Codigo de prueba: %s', $to, $code));

        return Command::SUCCESS;
    }

    private function fakeUser(string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->firstName = 'Usuario';
        $user->lastName = 'Prueba';
        $user->passwordHash = '';

        return $user;
    }
}
