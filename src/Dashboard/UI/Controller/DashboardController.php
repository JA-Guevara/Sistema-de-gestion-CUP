<?php

declare(strict_types=1);

namespace App\Dashboard\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Dashboard\Application\UseCase\GetReportes;
use App\Dashboard\Infrastructure\Persistence\ReporteRepository;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class DashboardController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly GetReportes $reportes,
        private readonly ReporteRepository $reporteRepo,
        private readonly GestionRepository $gestiones,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->resolveGestion($request);
        $filtros = $this->filtros($request);

        $payload = $gestion !== null
            ? $this->reportes->execute($gestion, $filtros['carrera'], $filtros['materia'], $filtros['docente'])
            : null;

        return $this->render('@dashboard/dashboard.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'gestiones' => $this->gestiones->listAll(),
            'payload' => $payload,
            'opciones' => $this->opciones($gestion),
            'filtros' => $filtros,
        ]);
    }

    #[Route('/dashboard/data', name: 'dashboard_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $gestion = $this->resolveGestion($request);
        if ($gestion === null) {
            return new JsonResponse(['error' => 'no_gestion'], Response::HTTP_NOT_FOUND);
        }

        $filtros = $this->filtros($request);

        return new JsonResponse(
            $this->reportes->execute($gestion, $filtros['carrera'], $filtros['materia'], $filtros['docente'])
        );
    }

    /** @return array{carreras: list<array<string,mixed>>, materias: list<array<string,mixed>>, docentes: list<array<string,mixed>>} */
    private function opciones(?Gestion $gestion): array
    {
        if ($gestion === null) {
            return ['carreras' => [], 'materias' => [], 'docentes' => []];
        }

        $gestionId = (int) $gestion->id;

        return [
            'carreras' => $this->reporteRepo->carrerasConEstudiantes($gestionId),
            'materias' => $this->reporteRepo->materiasConNotas($gestionId),
            'docentes' => array_map(static fn (array $d): array => [
                'id' => $d['id'],
                'nombre' => trim(((string) $d['lastName']) . ' ' . ((string) $d['firstName'])),
            ], $this->reporteRepo->docentesDeGestion($gestionId)),
        ];
    }

    private function resolveGestion(Request $request): ?Gestion
    {
        $id = $request->query->getInt('gestion');
        if ($id > 0) {
            $gestion = $this->gestiones->findById($id);
            if ($gestion !== null) {
                return $gestion;
            }
        }

        return $this->gestiones->findActive();
    }

    /** @return array{carrera: ?int, materia: ?int, docente: ?int} */
    private function filtros(Request $request): array
    {
        $opt = static fn (string $key): ?int => $request->query->getInt($key) > 0 ? $request->query->getInt($key) : null;

        return [
            'carrera' => $opt('carrera'),
            'materia' => $opt('materia'),
            'docente' => $opt('docente'),
        ];
    }

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
