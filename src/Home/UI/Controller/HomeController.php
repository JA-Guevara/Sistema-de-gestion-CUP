<?php

declare(strict_types=1);

namespace App\Home\UI\Controller;

use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Página de inicio del sistema (landing post-login).
 *
 * Es un "navigation hub": muestra al usuario su estado, los módulos
 * disponibles y los que aún están bloqueados. No tiene lógica de
 * negocio propia — depende de Auth para identificar al usuario.
 */
final class HomeController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(private readonly UserRepository $users)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        // No hay sesión iniciada: mandar al login.
        if (!is_int($userId)) {
            return $this->redirectToRoute('auth_login');
        }

        $user = $this->users->findById($userId);

        // Usuario eliminado entre requests: invalidar sesión y volver al login.
        if ($user === null) {
            $request->getSession()->invalidate();

            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@home/home.html.twig', ['user' => $user]);
    }
}
