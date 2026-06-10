<?php

declare(strict_types=1);

namespace App\Academico\Aula\UI\Controller;

use App\Academico\Aula\Application\DTO\AulaActionInput;
use App\Academico\Aula\Application\UseCase\CreateAula;
use App\Academico\Aula\Application\UseCase\CreateAulasMasivas;
use App\Academico\Aula\Application\UseCase\ListAulas;
use App\Academico\Aula\Application\UseCase\ShowAula;
use App\Academico\Aula\Application\UseCase\ToggleAulaState;
use App\Academico\Aula\Application\UseCase\UpdateAula;
use App\Academico\Aula\Application\UseCase\UpdateAulasStateBulk;
use App\Academico\Aula\Domain\Exception\AulaException;
use App\Academico\Aula\UI\Request\AulaRequest;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/aulas')]
final class AulaController extends AbstractController
{
    public function __construct(
        private readonly ListAulas $listAulas,
        private readonly ShowAula $showAula,
        private readonly CreateAula $createAula,
        private readonly CreateAulasMasivas $createAulasMasivas,
        private readonly UpdateAula $updateAula,
        private readonly ToggleAulaState $toggleAulaState,
        private readonly UpdateAulasStateBulk $updateAulasStateBulk,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'aula_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);

        return $this->render('@aula/lista.html.twig', ['aulas' => $this->listAulas->execute(), 'user' => $user]);
    }

    #[Route('/nueva', name: 'aula_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->saveAula($request);
    }

    #[Route('/generar', name: 'aula_generate', methods: ['POST'])]
    public function generate(Request $request): RedirectResponse
    {
        $this->requireUser($request);

        try {
            $aulas = $this->createAulasMasivas->execute(AulaRequest::massiveFromRequest($request));
            $this->addFlash('success', sprintf('Se crearon %d aulas correctamente.', count($aulas)));
        } catch (AulaException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('aula_new');
        }

        return $this->redirectToRoute('aula_index');
    }

    #[Route('/estado-masivo', name: 'aula_bulk_state', methods: ['POST'])]
    public function bulkState(Request $request): RedirectResponse
    {
        $this->requireUser($request);

        try {
            $aulas = $this->updateAulasStateBulk->execute(AulaRequest::bulkStateFromRequest($request));
            $this->addFlash('success', sprintf('Se actualizaron %d aulas correctamente.', count($aulas)));
        } catch (AulaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('aula_index');
    }

    #[Route('/{id}', name: 'aula_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): Response
    {
        return $this->render('@aula/detalle.html.twig', [
            'aula' => $this->showAula->execute($id),
            'user' => $this->requireUser($request),
        ]);
    }

    #[Route('/{id}/editar', name: 'aula_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        return $this->saveAula($request, $id);
    }

    #[Route('/{id}/estado', name: 'aula_toggle', methods: ['POST'])]
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->requireUser($request);
        $this->toggleAulaState->execute(new AulaActionInput($id, $this->actorUserId($request)));

        return $this->redirectToRoute('aula_index');
    }

    private function saveAula(Request $request, ?int $id = null): Response
    {
        $user = $this->requireUser($request);
        if (!$request->isMethod('POST')) {
            return $this->render('@aula/form.html.twig', ['aula' => $id ? $this->showAula->execute($id) : null, 'user' => $user]);
        }

        try {
            $id === null
                ? $this->createAula->execute(AulaRequest::fromRequest($request))
                : $this->updateAula->execute($id, AulaRequest::fromRequest($request));
            $this->addFlash('success', 'Aula guardada correctamente.');

            return $this->redirectToRoute('aula_index');
        } catch (AulaException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->render('@aula/form.html.twig', ['aula' => $id ? $this->showAula->execute($id) : null, 'user' => $user]);
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
