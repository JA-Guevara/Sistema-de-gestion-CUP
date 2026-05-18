<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\LoginUser;
use App\Auth\Domain\Exception\InvalidCredentials;
use App\Auth\UI\Request\LoginRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class LoginController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly LoginUser $loginUser
    ) {
    }

    #[Route('/login', name: 'auth_login')]
    public function login(Request $request): Response
    {
        // Si ya hay sesión, mandar a /me
        if ($request->getSession()->has(self::SESSION_USER_KEY)) {
            return $this->redirectToRoute('auth_me');
        }

        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/login.html.twig');
        }

        // Construir request DTO
        $input = new LoginRequest(
            email: trim(
                (string) $request->request->get('email', '')
            ),
            password: (string) $request->request->get('password', '')
        );

        try {
            $user = $this->loginUser->execute($input);
        } catch (InvalidCredentials $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->render('@auth/login.html.twig');
        }

        // Guardar sesión y redirigir
        $request->getSession()->set(self::SESSION_USER_KEY, $user->id);

        return $this->redirectToRoute('auth_me');
    }
}
