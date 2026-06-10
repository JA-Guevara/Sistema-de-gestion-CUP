<?php

declare(strict_types=1);

namespace App\Academico\Carrera\UI\Controller;

use App\Academico\Carrera\Application\DTO\CarreraActionInput;
use App\Academico\Carrera\Application\UseCase\ActivateCarrera;
use App\Academico\Carrera\Application\UseCase\CreateCarrera;
use App\Academico\Carrera\Application\UseCase\DeactivateCarrera;
use App\Academico\Carrera\Application\UseCase\ListCarreras;
use App\Academico\Carrera\Application\UseCase\ShowCarrera;
use App\Academico\Carrera\Application\UseCase\UpdateCarrera;
use App\Academico\Carrera\Domain\Exception\CarreraException;
use App\Academico\Carrera\UI\Request\CarreraRequest;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/carreras')]
final class CarreraController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly ListCarreras $listCarreras,
        private readonly ShowCarrera $showCarrera,
        private readonly CreateCarrera $createCarrera,
        private readonly UpdateCarrera $updateCarrera,
        private readonly ActivateCarrera $activateCarrera,
        private readonly DeactivateCarrera $deactivateCarrera,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'carrera_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@carrera/lista.html.twig', [
            'carreras' => $this->listCarreras->execute(),
            'user' => $user,
        ]);
    }

    #[Route('/nueva', name: 'carrera_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($request);
        }

        try {
            $this->createCarrera->execute(CarreraRequest::fromRequest($request));
            $this->addFlash('success', 'Carrera creada correctamente.');

            return $this->redirectToRoute('carrera_index');
        } catch (CarreraException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($request);
        }
    }

    #[Route('/{id}/editar', name: 'carrera_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($request, $id);
        }

        try {
            $this->updateCarrera->execute($id, CarreraRequest::fromRequest($request));
            $this->addFlash('success', 'Carrera actualizada correctamente.');

            return $this->redirectToRoute('carrera_index');
        } catch (CarreraException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($request, $id);
        }
    }

    #[Route('/{id}/activar', name: 'carrera_activate', methods: ['POST'])]
    public function activate(Request $request, int $id): RedirectResponse
    {
        return $this->runCarreraAction($request, $id, fn (CarreraActionInput $input) => $this->activateCarrera->execute($input));
    }

    #[Route('/{id}/desactivar', name: 'carrera_deactivate', methods: ['POST'])]
    public function deactivate(Request $request, int $id): RedirectResponse
    {
        return $this->runCarreraAction($request, $id, fn (CarreraActionInput $input) => $this->deactivateCarrera->execute($input));
    }

    private function renderFormView(Request $request, ?int $id = null): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@carrera/form.html.twig', [
            'carrera' => $id !== null ? $this->showCarrera->execute($id) : null,
            'user' => $user,
        ]);
    }

    private function runCarreraAction(Request $request, int $id, callable $action): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $action(new CarreraActionInput($id, $this->actorUserId($request)));
            $this->addFlash('success', 'Accion ejecutada correctamente.');
        } catch (CarreraException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('carrera_index');
    }

    private function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $userId : null;
    }

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
