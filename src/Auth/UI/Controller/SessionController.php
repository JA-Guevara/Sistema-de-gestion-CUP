<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\AuthEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class SessionController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthEvents $authEvents,
    ) {
    }

    #[Route('/logout', name: 'auth_logout', methods: ['GET'])]
    public function logout(Request $request): RedirectResponse
    {
        // Capturamos el user ANTES de invalidar para poder loguear el evento.
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);
        if (is_int($userId)) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $this->authEvents->logout($user);
            }
        }

        $request->getSession()->invalidate();

        return $this->redirectToRoute('auth_login');
    }
}
