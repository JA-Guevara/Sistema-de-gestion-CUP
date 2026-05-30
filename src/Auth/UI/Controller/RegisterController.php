<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\RegisterUser;
use App\Auth\Domain\Exception\EmailAlreadyRegistered;
use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Auth\UI\Request\RegisterRequest;
use App\Bitacora\Application\EventLog\AuthEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class RegisterController extends AbstractController
{
    private const CSRF_INTENTION = 'auth_register';

    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly CsrfManager $csrf,
        private readonly AuthEvents $authEvents,
    ) {
    }

    #[Route('/register', name: 'auth_register')]
    public function register(Request $request): Response
    {
        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/register.html.twig', [
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        }

        // Validar CSRF
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesión expiró. Volvé a intentarlo.');

            return $this->redirectToRoute('auth_register');
        }

        // Construir request DTO
        $input = new RegisterRequest(
            firstName: trim(
                (string) $request->request->get('firstName', '')
            ),
            lastName: trim(
                (string) $request->request->get('lastName', '')
            ),
            email: trim(
                (string) $request->request->get('email', '')
            ),
            password: (string) $request->request->get('password', '')
        );

        // Registrar usuario
        try {
            $user = $this->registerUser->execute($input);

            $this->authEvents->registroExitoso($user);

            $this->addFlash(
                'success',
                'Cuenta creada. Ya podés iniciar sesión.'
            );

            return $this->redirectToRoute('auth_login');

        } catch (
            InvalidRegistrationData |
            EmailAlreadyRegistered $exception
        ) {
            $this->addFlash(
                'error',
                $exception->getMessage()
            );

            return $this->redirectToRoute('auth_register');
        }
    }
}
