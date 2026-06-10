<?php

declare(strict_types=1);

namespace App\Academico\Materia\UI\Controller;

use App\Academico\Materia\Application\DTO\MateriaActionInput;
use App\Academico\Materia\Application\UseCase\CreateMateria;
use App\Academico\Materia\Application\UseCase\ListMaterias;
use App\Academico\Materia\Application\UseCase\ShowMateria;
use App\Academico\Materia\Application\UseCase\ToggleMateriaState;
use App\Academico\Materia\Application\UseCase\UpdateMateria;
use App\Academico\Materia\Domain\Exception\MateriaException;
use App\Academico\Materia\UI\Request\MateriaRequest;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/materias')]
final class MateriaController extends AbstractController
{
    public function __construct(
        private readonly ListMaterias $listMaterias,
        private readonly ShowMateria $showMateria,
        private readonly CreateMateria $createMateria,
        private readonly UpdateMateria $updateMateria,
        private readonly ToggleMateriaState $toggleMateriaState,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'materia_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);

        return $this->render('@materia/lista.html.twig', ['materias' => $this->listMaterias->execute(), 'user' => $user]);
    }

    #[Route('/nueva', name: 'materia_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->saveMateria($request);
    }

    #[Route('/{id}/editar', name: 'materia_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        return $this->saveMateria($request, $id);
    }

    #[Route('/{id}/estado', name: 'materia_toggle', methods: ['POST'])]
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->requireUser($request);
        $this->toggleMateriaState->execute(new MateriaActionInput($id, $this->actorUserId($request)));

        return $this->redirectToRoute('materia_index');
    }

    private function saveMateria(Request $request, ?int $id = null): Response
    {
        $user = $this->requireUser($request);
        if (!$request->isMethod('POST')) {
            return $this->render('@materia/form.html.twig', ['materia' => $id ? $this->showMateria->execute($id) : null, 'user' => $user]);
        }

        try {
            $id === null
                ? $this->createMateria->execute(MateriaRequest::fromRequest($request))
                : $this->updateMateria->execute($id, MateriaRequest::fromRequest($request));
            $this->addFlash('success', 'Materia guardada correctamente.');

            return $this->redirectToRoute('materia_index');
        } catch (MateriaException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->render('@materia/form.html.twig', ['materia' => $id ? $this->showMateria->execute($id) : null, 'user' => $user]);
        }
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
