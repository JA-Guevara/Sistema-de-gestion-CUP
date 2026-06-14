<?php

declare(strict_types=1);

namespace App\Usuario\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Usuario\Application\UseCase\CreateRole;
use App\Usuario\Application\UseCase\ListPermissions;
use App\Usuario\Application\UseCase\ListRoles;
use App\Usuario\Application\UseCase\ShowRole;
use App\Usuario\Application\UseCase\ToggleRoleState;
use App\Usuario\Application\UseCase\UpdateRole;
use App\Usuario\Domain\Entity\Role;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\UI\Request\RoleRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/roles')]
final class RoleController extends AbstractController
{
    public function __construct(
        private readonly ListRoles $listRoles,
        private readonly ListPermissions $listPermissions,
        private readonly ShowRole $showRole,
        private readonly CreateRole $createRole,
        private readonly UpdateRole $updateRole,
        private readonly ToggleRoleState $toggleRoleState,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'rol_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('@usuario/roles/lista.html.twig', [
            'roles' => $this->listRoles->execute(),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/nuevo', name: 'rol_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser($request);

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($user);
        }

        try {
            $role = $this->createRole->execute(RoleRequest::fromRequest($request));
            $this->addFlash('success', 'Rol creado correctamente.');

            return $this->redirectToRoute('rol_show', ['id' => $role->id]);
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($user);
        }
    }

    #[Route('/{id}', name: 'rol_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): Response
    {
        return $this->render('@usuario/roles/detalle.html.twig', [
            'rol' => $this->showRole->execute($id),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/{id}/editar', name: 'rol_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->requireUser($request);
        $role = $this->showRole->execute($id);

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($user, $role);
        }

        try {
            $updated = $this->updateRole->execute($id, RoleRequest::fromRequest($request));
            $this->addFlash('success', 'Rol actualizado correctamente.');

            return $this->redirectToRoute('rol_show', ['id' => $updated->id]);
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($user, $role);
        }
    }

    #[Route('/{id}/estado', name: 'rol_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->requireUser($request);

        try {
            $this->toggleRoleState->execute(RoleRequest::actionFromRequest($request, $id));
            $this->addFlash('success', 'Estado del rol actualizado correctamente.');
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('rol_index');
    }

    private function renderFormView(User $user, ?Role $role = null): Response
    {
        return $this->render('@usuario/roles/form.html.twig', [
            'rol' => $role,
            'permissionsGrouped' => $this->listPermissions->execute(),
            'user' => $user,
        ]);
    }

    private function requireUser(Request $request): User
    {
        $userId = $request->getSession()->get('auth_user_id');
        $user = is_int($userId) ? $this->users->findById($userId) : null;
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
