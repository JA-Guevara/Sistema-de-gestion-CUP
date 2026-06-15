<?php

declare(strict_types=1);

namespace App\Academico\Grupo\UI\Controller;

use App\Academico\Grupo\Application\DTO\GrupoActionInput;
use App\Academico\Grupo\Application\UseCase\CreateGrupo;
use App\Academico\Grupo\Application\UseCase\GenerateGrupos;
use App\Academico\Grupo\Application\UseCase\ListGrupos;
use App\Academico\Grupo\Application\UseCase\ShowGrupo;
use App\Academico\Grupo\Application\UseCase\ToggleGrupoState;
use App\Academico\Grupo\Application\UseCase\UpdateGrupo;
use App\Academico\Grupo\Application\UseCase\UpdateGruposStateBulk;
use App\Academico\Grupo\Domain\Exception\GrupoException;
use App\Academico\Grupo\UI\Request\GrupoRequest;
use App\Academico\Turno\Infrastructure\Persistence\TurnoRepository;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/grupos')]
final class GrupoController extends AbstractController
{
    public function __construct(
        private readonly ListGrupos $listGrupos,
        private readonly ShowGrupo $showGrupo,
        private readonly CreateGrupo $createGrupo,
        private readonly UpdateGrupo $updateGrupo,
        private readonly ToggleGrupoState $toggleGrupoState,
        private readonly UpdateGruposStateBulk $updateGruposStateBulk,
        private readonly GenerateGrupos $generateGrupos,
        private readonly GestionRepository $gestiones,
        private readonly TurnoRepository $turnos,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'grupo_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('@grupo/lista.html.twig', [
            'grupos' => $this->listGrupos->execute(),
            'gestiones' => $this->gestiones->listAll(),
            'turnos' => $this->turnos->listActive(),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/nuevo', name: 'grupo_new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        $this->requireUser($request);
        try {
            $this->createGrupo->execute(GrupoRequest::fromRequest($request));
            $this->addFlash('success', 'Grupo creado correctamente.');
        } catch (GrupoException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('grupo_index');
    }

    #[Route('/{id}', name: 'grupo_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): Response
    {
        return $this->render('@grupo/detalle.html.twig', [
            'grupo' => $this->showGrupo->execute($id),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/{id}/editar', name: 'grupo_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->requireUser($request);
        $grupo = $this->showGrupo->execute($id);

        if (!$request->isMethod('POST')) {
            return $this->render('@grupo/form.html.twig', [
                'grupo' => $grupo,
                'gestiones' => $this->gestiones->listAll(),
                'turnos' => $this->turnos->listActive(),
                'user' => $user,
            ]);
        }

        try {
            $this->updateGrupo->execute($id, GrupoRequest::fromRequest($request));
            $this->addFlash('success', 'Grupo actualizado correctamente.');

            return $this->redirectToRoute('grupo_show', ['id' => $id]);
        } catch (GrupoException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->render('@grupo/form.html.twig', [
                'grupo' => $grupo,
                'gestiones' => $this->gestiones->listAll(),
                'turnos' => $this->turnos->listActive(),
                'user' => $user,
            ]);
        }
    }

    #[Route('/{id}/estado', name: 'grupo_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, int $id): Response
    {
        $this->requireUser($request);

        try {
            $this->toggleGrupoState->execute(new GrupoActionInput($id, GrupoRequest::actorUserId($request)));
            $this->addFlash('success', 'Estado del grupo actualizado correctamente.');
        } catch (GrupoException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('grupo_index');
    }

    #[Route('/estado-masivo', name: 'grupo_bulk_state', methods: ['POST'])]
    public function bulkState(Request $request): Response
    {
        $this->requireUser($request);

        try {
            $grupos = $this->updateGruposStateBulk->execute(GrupoRequest::bulkStateFromRequest($request));
            $this->addFlash('success', sprintf('Se actualizaron %d grupos correctamente.', count($grupos)));
        } catch (GrupoException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('grupo_index');
    }

    #[Route('/generar', name: 'grupo_generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        $this->requireUser($request);
        try {
            $this->generateGrupos->execute(GrupoRequest::generateFromRequest($request));
            $this->addFlash('success', 'Grupos generados correctamente.');
        } catch (GrupoException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('grupo_index');
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
