<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\ResetPassword;
use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Domain\Exception\PasswordResetTokenExpired;
use App\Auth\Domain\Exception\PasswordResetTokenInvalid;
use App\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Auth\UI\Request\ResetPasswordRequest;
use App\Bitacora\Application\EventLog\AuthEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class ResetPasswordController extends AbstractController
{
    private const CSRF_INTENTION = 'auth_reset';

    public function __construct(
        private readonly ResetPassword $resetPassword,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly CsrfManager $csrf,
        private readonly AuthEvents $authEvents,
    ) {
    }

    #[Route('/reset/{token}', name: 'auth_reset', requirements: ['token' => '[a-f0-9]{64}'])]
    public function reset(Request $request, string $token): Response
    {
        // Pre-chequeo: el token debe existir y estar vigente para mostrar el form.
        $resetToken = $this->tokens->findByToken($token);

        if ($resetToken === null || $resetToken->isUsed() || $resetToken->isExpired()) {
            $this->addFlash(
                'error',
                'El enlace de recuperación no es válido o ya expiró. Solicitá uno nuevo.'
            );

            return $this->redirectToRoute('auth_forgot');
        }

        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/reset.html.twig', [
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
                'token' => $token,
            ]);
        }

        // Validar CSRF
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesión expiró. Volvé a intentarlo.');

            return $this->redirectToRoute('auth_reset', ['token' => $token]);
        }

        // Aplicar el reset
        try {
            $this->resetPassword->execute(new ResetPasswordRequest(
                token: $token,
                password: (string) $request->request->get('password', ''),
                passwordConfirmation: (string) $request->request->get('passwordConfirmation', ''),
            ));
        } catch (
            PasswordResetTokenInvalid |
            PasswordResetTokenExpired $exception
        ) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_forgot');
        } catch (InvalidRegistrationData $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_reset', ['token' => $token]);
        }

        // Log de éxito (busco el user que pertenecía al token).
        $usedToken = $this->tokens->findByToken($token);
        if ($usedToken !== null) {
            $user = $this->users->findById($usedToken->userId);
            if ($user !== null) {
                $this->authEvents->passwordResetCompletado($user);
            }
        }

        $this->addFlash(
            'success',
            'Contraseña actualizada. Iniciá sesión con la nueva.'
        );

        return $this->redirectToRoute('auth_login');
    }
}
