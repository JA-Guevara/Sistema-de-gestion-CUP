<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\ForgotPassword;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Auth\UI\Request\ForgotPasswordRequest;
use App\Bitacora\Application\EventLog\AuthEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class ForgotPasswordController extends AbstractController
{
    private const CSRF_INTENTION = 'auth_forgot';

    public function __construct(
        private readonly ForgotPassword $forgotPassword,
        private readonly CsrfManager $csrf,
        private readonly AuthEvents $authEvents,
    ) {
    }

    #[Route('/forgot', name: 'auth_forgot')]
    public function forgot(Request $request): Response
    {
        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/forgot.html.twig', [
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        }

        // Validar CSRF
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesión expiró. Volvé a intentarlo.');

            return $this->redirectToRoute('auth_forgot');
        }

        $email = trim((string) $request->request->get('email', ''));

        // Disparar el use case (no falla si el email no existe)
        $this->forgotPassword->execute(new ForgotPasswordRequest(email: $email));

        $this->authEvents->passwordResetSolicitado($email);

        // Mensaje neutral (no revela si el email existía o no)
        $this->addFlash(
            'success',
            'Si el correo está registrado, te enviamos un enlace de recuperación. Revisá tu casilla.'
        );

        return $this->redirectToRoute('auth_login');
    }
}
