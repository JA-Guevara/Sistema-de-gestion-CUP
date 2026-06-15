<?php

declare(strict_types=1);

namespace App\Asignacion\UI\Controller;

use App\Asignacion\Application\UseCase\ShowAsignacionDashboard;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hub de "Asignaciones": agrupa la asignacion de docentes (materia + grupo),
 * la asignacion de estudiantes a grupos y los horarios.
 */
#[Route('/asignaciones')]
final class AsignacionController extends AbstractController
{
    public function __construct(
        private readonly ShowAsignacionDashboard $showDashboard,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'asignacion_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@asignacion/index.html.twig', array_merge(
            $this->showDashboard->execute(),
            ['user' => $user],
        ));
    }

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
