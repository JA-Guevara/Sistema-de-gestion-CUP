<?php

declare(strict_types=1);

namespace App\Gestion\UI\Controller;

use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Application\DTO\GestionActionInput;
use App\Gestion\Application\UseCase\ActivateGestion;
use App\Gestion\Application\UseCase\CloseInscription;
use App\Gestion\Application\UseCase\CreateGestion;
use App\Gestion\Application\UseCase\GetGestionDashboard;
use App\Gestion\Application\UseCase\ListGestiones;
use App\Gestion\Application\UseCase\OpenInscription;
use App\Gestion\Application\UseCase\ShowGestion;
use App\Gestion\Application\UseCase\UpdateGestion;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Domain\Exception\GestionException;
use App\Gestion\UI\Request\GestionRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/gestiones')]
final class GestionController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly ListGestiones $listGestiones,
        private readonly ShowGestion $showGestion,
        private readonly GetGestionDashboard $dashboard,
        private readonly CreateGestion $createGestion,
        private readonly UpdateGestion $updateGestion,
        private readonly ActivateGestion $activateGestion,
        private readonly OpenInscription $openInscription,
        private readonly CloseInscription $closeInscription,
        private readonly UserRepository $users,
        private readonly CarreraRepository $carreras,
    ) {
    }

    #[Route('', name: 'gestion_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@gestion/lista.html.twig', [
            'gestiones' => $this->listGestiones->execute(),
            'dashboard' => $this->dashboard->execute(),
            'user' => $user,
        ]);
    }

    #[Route('/nueva', name: 'gestion_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($request);
        }

        try {
            $gestion = $this->createGestion->execute(GestionRequest::fromRequest($request));
            $this->addFlash('success', 'Gestion creada correctamente.');

            return $this->redirectToRoute('gestion_show', ['id' => $gestion->id]);
        } catch (GestionException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($request);
        }
    }

    #[Route('/{id}', name: 'gestion_show', methods: ['GET'])]
    public function show(Request $request, int $id): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@gestion/detalle.html.twig', [
            'gestion' => $this->showGestion->execute($id),
            'estados' => EstadoGestion::class,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/editar', name: 'gestion_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$request->isMethod('POST')) {
            return $this->renderFormView($request, $id);
        }

        try {
            $gestion = $this->updateGestion->execute($id, GestionRequest::fromRequest($request));
            $this->addFlash('success', 'Gestion actualizada correctamente.');

            return $this->redirectToRoute('gestion_show', ['id' => $gestion->id]);
        } catch (GestionException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormView($request, $id);
        }
    }

    #[Route('/{id}/activar', name: 'gestion_activate', methods: ['POST'])]
    public function activate(Request $request, int $id): RedirectResponse
    {
        return $this->runGestionAction($request, $id, fn (GestionActionInput $input) => $this->activateGestion->execute($input));
    }

    #[Route('/{id}/abrir-inscripcion', name: 'gestion_open_inscription', methods: ['POST'])]
    public function openInscription(Request $request, int $id): RedirectResponse
    {
        return $this->runGestionAction($request, $id, fn (GestionActionInput $input) => $this->openInscription->execute($input));
    }

    #[Route('/{id}/cerrar-inscripcion', name: 'gestion_close_inscription', methods: ['POST'])]
    public function closeInscription(Request $request, int $id): RedirectResponse
    {
        return $this->runGestionAction($request, $id, fn (GestionActionInput $input) => $this->closeInscription->execute($input));
    }

    private function renderFormView(Request $request, ?int $id = null): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@gestion/form.html.twig', [
            'gestion' => $id !== null ? $this->showGestion->execute($id) : null,
            'catalogCareers' => $this->carreras->listActive(),
            'user' => $user,
        ]);
    }

    private function runGestionAction(Request $request, int $id, callable $action): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $action(new GestionActionInput($id, $this->actorUserId($request)));
            $this->addFlash('success', 'Accion ejecutada correctamente.');
        } catch (GestionException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('gestion_show', ['id' => $id]);
    }

    private function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $userId : null;
    }

    private function currentUser(Request $request): ?\App\Auth\Entity\User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
