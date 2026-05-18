<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class SessionController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly UserRepository $users
    ) {
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(Request $request): Response
    {
        $userId = $request
            ->getSession()
            ->get(self::SESSION_USER_KEY);

        // Usuario no logueado
        if (!is_int($userId)) {
            return $this->redirectToRoute('auth_login');
        }

        // Buscar usuario
        $user = $this->users->findById($userId);

        // Usuario inexistente
        if ($user === null) {

            $request->getSession()->invalidate();

            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@auth/me.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function logout(Request $request): RedirectResponse
    {
        $request->getSession()->invalidate();

        return $this->redirectToRoute('auth_login');
    }
}