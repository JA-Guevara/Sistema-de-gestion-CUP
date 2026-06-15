<?php

declare(strict_types=1);

namespace App\Dashboard\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Dashboard\Application\UseCase\ExportarReportes;
use App\Dashboard\Application\UseCase\GetReportes;
use App\Dashboard\Infrastructure\Persistence\ReporteRepository;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exportación completa de los reportes (CSV con secciones / Excel multi-hoja).
 * Separado del DashboardController para no acoplarse a su evolución.
 */
#[Route('/admin')]
final class ExportController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly GetReportes $reportes,
        private readonly ExportarReportes $exportar,
        private readonly ReporteRepository $reporteRepo,
        private readonly GestionRepository $gestiones,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/dashboard/export', name: 'dashboard_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->resolveGestion($request);
        if ($gestion === null) {
            return new Response('No hay una gestión activa.', Response::HTTP_NOT_FOUND);
        }

        $gestionId = (int) $gestion->id;
        $carreraId = $this->opt($request, 'carrera');
        $materiaId = $this->opt($request, 'materia');
        $docenteId = $this->opt($request, 'docente');
        $estado = strtoupper(trim((string) $request->query->get('estado', '')));
        $texto = trim((string) $request->query->get('texto', ''));
        $formato = (string) $request->query->get('formato', 'csv');

        $payload = $this->reportes->execute($gestion, $carreraId, $materiaId, $docenteId);
        $payload['detalle'] = $this->filtrarDetalle($payload['detalle'], $estado, $texto);

        $contexto = [
            'titulo' => 'CUP FICCT · Reporte de admisión',
            'gestion' => $gestion->codigo . ' — ' . $gestion->nombre,
            'fecha' => date('d/m/Y H:i'),
            'filtros' => $this->filtrosLabels($gestionId, $carreraId, $materiaId, $docenteId, $estado, $texto),
        ];

        $base = 'reporte_cup_' . preg_replace('/[^A-Za-z0-9]+/', '-', $gestion->codigo) . '_' . date('Ymd_Hi');

        if ($formato === 'xlsx' || $formato === 'excel') {
            $contenido = $this->exportar->excel($payload, $contexto);

            return $this->descarga($contenido, $base . '.xls', 'application/vnd.ms-excel; charset=UTF-8');
        }

        $contenido = "\xEF\xBB\xBF" . $this->exportar->csv($payload, $contexto);

        return $this->descarga($contenido, $base . '.csv', 'text/csv; charset=UTF-8');
    }

    private function descarga(string $contenido, string $filename, string $contentType): Response
    {
        $response = new Response($contenido);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * @param list<array<string, mixed>> $detalle
     * @return list<array<string, mixed>>
     */
    private function filtrarDetalle(array $detalle, string $estado, string $texto): array
    {
        if ($estado === '' && $texto === '') {
            return $detalle;
        }

        $t = mb_strtolower($texto);

        return array_values(array_filter($detalle, static function (array $r) use ($estado, $t): bool {
            if ($estado !== '' && (string) $r['estado'] !== $estado) {
                return false;
            }
            if ($t !== '' && !str_contains(mb_strtolower(((string) $r['ci']) . ' ' . ((string) $r['nombre'])), $t)) {
                return false;
            }

            return true;
        }));
    }

    /** @return array<string, string> */
    private function filtrosLabels(int $gestionId, ?int $carreraId, ?int $materiaId, ?int $docenteId, string $estado, string $texto): array
    {
        $labels = [
            'Carrera' => $this->nombrePorId($this->reporteRepo->carrerasConEstudiantes($gestionId), $carreraId, 'Todas'),
            'Materia' => $this->nombrePorId($this->reporteRepo->materiasConNotas($gestionId), $materiaId, 'Todas'),
            'Docente' => $this->docentePorId($gestionId, $docenteId),
        ];
        if ($estado !== '') {
            $labels['Estado'] = ucfirst(strtolower($estado));
        }
        if ($texto !== '') {
            $labels['Búsqueda'] = $texto;
        }

        return $labels;
    }

    /** @param list<array<string, mixed>> $items */
    private function nombrePorId(array $items, ?int $id, string $defecto): string
    {
        if ($id === null) {
            return $defecto;
        }
        foreach ($items as $it) {
            if ((int) $it['id'] === $id) {
                return (string) $it['nombre'];
            }
        }

        return $defecto;
    }

    private function docentePorId(int $gestionId, ?int $docenteId): string
    {
        if ($docenteId === null) {
            return 'Todos';
        }
        foreach ($this->reporteRepo->docentesDeGestion($gestionId) as $d) {
            if ((int) $d['id'] === $docenteId) {
                return trim(((string) $d['lastName']) . ' ' . ((string) $d['firstName']));
            }
        }

        return 'Todos';
    }

    private function opt(Request $request, string $key): ?int
    {
        return $request->query->getInt($key) > 0 ? $request->query->getInt($key) : null;
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

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
