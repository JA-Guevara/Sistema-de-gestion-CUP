<?php

declare(strict_types=1);

namespace App\Usuario\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Usuario\Application\DTO\UsuariosRolBulkInput;
use App\Usuario\Application\UseCase\AssignRoleToUsersBulk;
use App\Usuario\Application\UseCase\CreateUser;
use App\Usuario\Application\UseCase\ListRoles;
use App\Usuario\Application\UseCase\ListUsers;
use App\Usuario\Application\UseCase\ResetUserPassword;
use App\Usuario\Application\UseCase\ShowUser;
use App\Usuario\Application\UseCase\ToggleUserState;
use App\Usuario\Application\UseCase\UpdateUser;
use App\Usuario\Domain\Exception\UsuarioException;
use App\Usuario\UI\Request\UsuarioRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/usuarios')]
final class UsuarioController extends AbstractController
{
    public function __construct(
        private readonly ListUsers $listUsers,
        private readonly ShowUser $showUser,
        private readonly CreateUser $createUser,
        private readonly UpdateUser $updateUser,
        private readonly ToggleUserState $toggleUserState,
        private readonly ResetUserPassword $resetUserPassword,
        private readonly ListRoles $listRoles,
        private readonly AssignRoleToUsersBulk $assignRoleToUsersBulk,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'usuario_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('@usuario/usuarios/lista.html.twig', [
            'usuarios' => $this->listUsers->execute(),
            'roles' => $this->listRoles->execute(),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/roles', name: 'usuario_roles_bulk', methods: ['POST'])]
    public function rolesBulk(Request $request): RedirectResponse
    {
        $this->requireUser($request);

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

        return $this->redirectToRoute('usuario_index');
    }

    #[Route('/nuevo', name: 'usuario_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser($request);

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($user);
        }

        try {
            $created = $this->createUser->execute(UsuarioRequest::fromRequest($request, true));
            $this->addFlash('success', 'Usuario creado correctamente.');

            return $this->redirectToRoute('usuario_show', ['id' => $created->id]);
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($user);
        }
    }

    #[Route('/{id}', name: 'usuario_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): Response
    {
        return $this->render('@usuario/usuarios/detalle.html.twig', [
            'usuario' => $this->showUser->execute($id),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/{id}/editar', name: 'usuario_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->requireUser($request);
        $usuario = $this->showUser->execute($id);

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($user, $usuario);
        }

        try {
            $updated = $this->updateUser->execute($id, UsuarioRequest::fromRequest($request, false));
            $this->addFlash('success', 'Usuario actualizado correctamente.');

            return $this->redirectToRoute('usuario_show', ['id' => $updated->id]);
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($user, $usuario);
        }
    }

    #[Route('/{id}/estado', name: 'usuario_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->requireUser($request);

        try {
            $this->toggleUserState->execute(UsuarioRequest::actionFromRequest($request, $id));
            $this->addFlash('success', 'Estado del usuario actualizado correctamente.');
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('usuario_index');
    }

    #[Route('/{id}/reset-password', name: 'usuario_reset_password', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $this->requireUser($request);

        try {
            $this->resetUserPassword->execute(UsuarioRequest::actionFromRequest($request, $id));
            $this->addFlash('success', 'Enlace de recuperacion enviado al correo del usuario.');
        } catch (UsuarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('usuario_show', ['id' => $id]);
    }

    private function renderFormView(User $user, ?User $usuario = null): Response
    {
        return $this->render('@usuario/usuarios/form.html.twig', [
            'usuario' => $usuario,
            'roles' => $this->listRoles->execute(),
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

    private function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
