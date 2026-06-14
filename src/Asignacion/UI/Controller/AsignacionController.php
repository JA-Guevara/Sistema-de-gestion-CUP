<?php

declare(strict_types=1);

namespace App\Asignacion\UI\Controller;

use App\Asignacion\Application\UseCase\ShowAsignacionDashboard;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Usuario\Application\DTO\UsuariosRolBulkInput;
use App\Usuario\Application\UseCase\AssignRoleToUsersBulk;
use App\Usuario\Domain\Exception\UsuarioException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private readonly AssignRoleToUsersBulk $assignRoleToUsersBulk,
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

    #[Route('/roles', name: 'asignacion_roles_bulk', methods: ['POST'])]
    public function assignRoles(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $updated = $this->assignRoleToUsersBulk->execute(new UsuariosRolBulkInput(
                roleId: (int) $request->request->get('roleId', 0),
                userIds: array_map('intval', (array) $request->request->all('usuarios')),
                actorUserId: $this->actorUserId($request),
            ));
            $this->addFlash('success', sprintf('Rol asignado a %d usuario(s).', $updated));
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('asignacion_index');
    }

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $this->users->findById($userId) : null;
    }

    private function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
