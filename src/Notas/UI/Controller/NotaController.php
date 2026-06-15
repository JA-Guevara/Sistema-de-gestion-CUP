<?php

declare(strict_types=1);

namespace App\Notas\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Application\DTO\GuardarNotasInput;
use App\Notas\Application\UseCase\GuardarNotas;
use App\Notas\Application\UseCase\ImportarNotas;
use App\Notas\Application\UseCase\ListAsignaciones;
use App\Notas\Application\UseCase\ListMateriasDocente;
use App\Notas\Application\UseCase\VerBoletin;
use App\Notas\Application\UseCase\VerHorarioEstudiante;
use App\Notas\Application\UseCase\VerPlanilla;
use App\Notas\Domain\Exception\NotaException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modulo de Notas: planilla de calificaciones, boletin y horario del estudiante,
 * y "Mis materias" del docente. La asignacion de docentes y estudiantes a grupos
 * vive en el modulo Asignaciones (/asignaciones).
 */
#[Route('/notas')]
final class NotaController extends AbstractController
{
    public function __construct(
        private readonly ListAsignaciones $listAsignaciones,
        private readonly ListMateriasDocente $listMateriasDocente,
        private readonly VerPlanilla $verPlanilla,
        private readonly GuardarNotas $guardarNotas,
        private readonly ImportarNotas $importarNotas,
        private readonly VerBoletin $verBoletin,
        private readonly VerHorarioEstudiante $verHorarioEstudiante,
        private readonly GestionRepository $gestiones,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'nota_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        // El menú tiene un único item "Notas": cada rol llega a su vista.
        $puedeAsignar = $user->hasPermission('asignaciones.gestionar') || $user->hasPermission('notas.asignar');
        $puedeRegistrar = $user->hasPermission('notas.gestionar') || $user->hasPermission('notas.registrar');

        if (!$puedeAsignar) {
            return $puedeRegistrar
                ? $this->redirectToRoute('nota_docente')
                : $this->redirectToRoute('nota_boletin');
        }

        $gestion = $this->gestiones->findActive();

        return $this->render('@notas/index.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'asignaciones' => $gestion !== null ? $this->listAsignaciones->execute($gestion->id) : [],
        ]);
    }

    #[Route('/mis-materias', name: 'nota_docente', methods: ['GET'])]
    public function misMaterias(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->gestiones->findActive();

        return $this->render('@notas/docente.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'asignaciones' => $gestion !== null ? $this->listMateriasDocente->execute((int) $user->id, $gestion->id) : [],
        ]);
    }

    #[Route('/materia/{materiaId}/grupo/{grupoId}/planilla', name: 'nota_planilla', methods: ['GET'])]
    public function planilla(Request $request, int $materiaId, int $grupoId): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $data = $this->verPlanilla->execute($materiaId, $grupoId, $this->actorUserId($request));
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('nota_index');
        }

        return $this->render('@notas/planilla.html.twig', array_merge($data, ['user' => $user]));
    }

    #[Route('/materia/{materiaId}/grupo/{grupoId}/guardar', name: 'nota_guardar', methods: ['POST'])]
    public function guardar(Request $request, int $materiaId, int $grupoId): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $raw = $request->request->all('nota');

        try {
            $cantidad = $this->guardarNotas->execute(new GuardarNotasInput(
                materiaId: $materiaId,
                grupoId: $grupoId,
                valores: is_array($raw) ? $raw : [],
                actorUserId: $this->actorUserId($request),
            ));
            $this->addFlash('success', sprintf('Se guardaron %d nota(s) correctamente.', $cantidad));
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('nota_planilla', ['materiaId' => $materiaId, 'grupoId' => $grupoId]);
    }

    #[Route('/materia/{materiaId}/grupo/{grupoId}/exportar', name: 'nota_planilla_exportar', methods: ['GET'])]
    public function exportarNotas(Request $request, int $materiaId, int $grupoId): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $data = $this->verPlanilla->execute($materiaId, $grupoId, $this->actorUserId($request));
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('nota_index');
        }

        $cantidad = $data['cantidadExamenes'];
        $handle = fopen('php://temp', 'r+');
        $cabecera = ['CI', 'Apellidos', 'Nombres'];
        for ($e = 1; $e <= $cantidad; $e++) {
            $cabecera[] = 'Examen ' . $e;
        }
        $cabecera[] = 'Promedio';
        $cabecera[] = 'Estado';
        fputcsv($handle, $cabecera);

        foreach ($data['filas'] as $fila) {
            $row = [$fila['inscripcion']->ci, $fila['inscripcion']->apellidos, $fila['inscripcion']->nombres];
            for ($e = 1; $e <= $cantidad; $e++) {
                $row[] = $fila['valores'][$e] ?? '';
            }
            $row[] = $fila['promedio'] ?? '';
            $row[] = $fila['aprobado'] === null ? 'Incompleto' : ($fila['aprobado'] ? 'Aprobado' : 'Reprobado');
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $filename = sprintf('notas_%s_%s.csv', $data['materia']->codigo, $data['grupo']->codigo);

        return new Response("\xEF\xBB\xBF" . $csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/materia/{materiaId}/grupo/{grupoId}/importar', name: 'nota_planilla_importar', methods: ['POST'])]
    public function importarNotas(Request $request, int $materiaId, int $grupoId): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $file = $request->files->get('archivo');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'No se recibio ningun archivo CSV.');

            return $this->redirectToRoute('nota_planilla', ['materiaId' => $materiaId, 'grupoId' => $grupoId]);
        }

        try {
            $cantidad = $this->importarNotas->execute($materiaId, $grupoId, $file, $this->actorUserId($request));
            $this->addFlash('success', sprintf('Se importaron %d nota(s) desde el archivo.', $cantidad));
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('nota_planilla', ['materiaId' => $materiaId, 'grupoId' => $grupoId]);
    }

    #[Route('/boletin', name: 'nota_boletin', methods: ['GET'])]
    public function boletin(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@notas/boletin.html.twig', [
            'user' => $user,
            'boletin' => $this->verBoletin->execute((int) $user->id),
        ]);
    }

    #[Route('/mi-horario', name: 'nota_horario', methods: ['GET'])]
    public function miHorario(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@notas/horario.html.twig', [
            'user' => $user,
            'horario' => $this->verHorarioEstudiante->execute((int) $user->id),
        ]);
    }

    private function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $this->users->findById($userId) : null;
    }
}
