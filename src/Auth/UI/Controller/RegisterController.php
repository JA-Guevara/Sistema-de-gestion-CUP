<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\RegisterUser;
use App\Auth\Domain\Exception\EmailAlreadyRegistered;
use App\Auth\Domain\Exception\InvalidRegistrationData;
use App\Auth\UI\Request\RegisterRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly RegisterUser $registerUser
    ) {
    }

    #[Route('/register', name: 'auth_register')]
    public function register(Request $request): Response
    {
        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/register.html.twig');
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

        try {

            // Registrar usuario
            $this->registerUser->execute($input);

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

            return $this->render('@auth/register.html.twig');
        }
    }
}