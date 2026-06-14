<?php

declare(strict_types=1);

namespace App\Notas\UI\Controller;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Application\DTO\AsignarGrupoInput;
use App\Notas\Application\DTO\AsignarGruposMasivoInput;
use App\Notas\Application\DTO\GuardarNotasInput;
use App\Notas\Application\UseCase\AsignarDocente;
use App\Notas\Application\UseCase\AsignarInscritosMasivo;
use App\Notas\Application\UseCase\AsignarInscritosGrupo;
use App\Notas\Application\UseCase\DesasignarDocente;
use App\Notas\Application\UseCase\DesasignarInscritoGrupo;
use App\Notas\Application\UseCase\GuardarNotas;
use App\Notas\Application\UseCase\ImportarNotas;
use App\Notas\Application\UseCase\ListAsignaciones;
use App\Notas\Application\UseCase\ListMateriasDocente;
use App\Notas\Application\UseCase\VerBoletin;
use App\Notas\Application\UseCase\VerHorarioEstudiante;
use App\Notas\Application\UseCase\VerPlanilla;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;
use App\Notas\UI\Request\AsignacionRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notas')]
final class NotaController extends AbstractController
{
    public function __construct(
        private readonly ListAsignaciones $listAsignaciones,
        private readonly AsignarDocente $asignarDocente,
        private readonly DesasignarDocente $desasignarDocente,
        private readonly AsignarInscritosGrupo $asignarInscritosGrupo,
        private readonly AsignarInscritosMasivo $asignarInscritosMasivo,
        private readonly DesasignarInscritoGrupo $desasignarInscritoGrupo,
        private readonly ListMateriasDocente $listMateriasDocente,
        private readonly VerPlanilla $verPlanilla,
        private readonly GuardarNotas $guardarNotas,
        private readonly ImportarNotas $importarNotas,
        private readonly VerBoletin $verBoletin,
        private readonly VerHorarioEstudiante $verHorarioEstudiante,
        private readonly AsignacionDocenteRepository $asignacionesDocente,
        private readonly AsignacionGrupoRepository $asignacionesGrupo,
        private readonly MateriaRepository $materias,
        private readonly GrupoRepository $grupos,
        private readonly InscripcionRepository $inscripciones,
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
        if (!$user->hasPermission('notas.asignar')) {
            return $user->hasPermission('notas.registrar')
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

    #[Route('/asignaciones', name: 'nota_asignaciones', methods: ['GET', 'POST'])]
    public function asignaciones(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->gestiones->findActive();

        if ($request->isMethod('POST')) {
            try {
                $this->asignarDocente->execute(AsignacionRequest::fromRequest($request));
                $this->addFlash('success', 'Docente asignado correctamente.');
            } catch (NotaException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('nota_asignaciones');
        }

        return $this->render('@notas/asignaciones.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'asignaciones' => $gestion !== null ? $this->listAsignaciones->execute($gestion->id) : [],
            'materias' => $this->materias->listActive(),
            'grupos' => $gestion !== null ? $this->grupos->listByGestion($gestion->id) : [],
            'docentes' => $this->asignacionesDocente->listDocentes(),
        ]);
    }

    #[Route('/asignaciones/{id}/eliminar', name: 'nota_asignacion_delete', methods: ['POST'])]
    public function eliminarAsignacion(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $this->desasignarDocente->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Asignacion eliminada correctamente.');
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('nota_asignaciones');
    }

    #[Route('/grupos', name: 'nota_grupos', methods: ['GET', 'POST'])]
    public function grupos(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->gestiones->findActive();

        if ($request->isMethod('POST')) {
            $materiaId = (int) $request->request->get('materia', 0);
            $grupoId = (int) $request->request->get('grupo', 0);
            $ids = array_map('intval', (array) $request->request->all('inscritos'));

            try {
                $cantidad = $this->asignarInscritosGrupo->execute(new AsignarGrupoInput(
                    materiaId: $materiaId,
                    grupoId: $grupoId,
                    inscripcionIds: $ids,
                    actorUserId: $this->actorUserId($request),
                ));
                $this->addFlash('success', sprintf('%d estudiante(s) asignado(s) al grupo.', $cantidad));
            } catch (NotaException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('nota_grupos', ['materia' => $materiaId, 'grupo' => $grupoId]);
        }

        $materiaId = (int) $request->query->get('materia', 0);
        $grupoId = (int) $request->query->get('grupo', 0);

        $asignados = [];
        $disponibles = [];
        if ($gestion !== null && $materiaId > 0 && $grupoId > 0) {
            $asignados = $this->asignacionesGrupo->listByMateriaAndGrupo($materiaId, $grupoId);

            $enEsteGrupo = [];
            foreach ($asignados as $asignacion) {
                $enEsteGrupo[$asignacion->inscripcion->id] = true;
            }

            $grupoActualPorInscrito = [];
            foreach ($this->asignacionesGrupo->listByMateriaAndGestion($materiaId, $gestion->id) as $asignacion) {
                $grupoActualPorInscrito[$asignacion->inscripcion->id] = $asignacion->grupo->codigo;
            }

            $candidatos = $this->inscripciones->listByGestionTipoEstado($gestion->id, TipoPostulacion::ESTUDIANTE, EstadoInscripcion::CONFIRMADA);
            foreach ($candidatos as $inscripcion) {
                if (isset($enEsteGrupo[$inscripcion->id])) {
                    continue;
                }
                $disponibles[] = [
                    'inscripcion' => $inscripcion,
                    'grupoActual' => $grupoActualPorInscrito[$inscripcion->id] ?? null,
                ];
            }
        }

        return $this->render('@notas/grupos.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'materias' => $this->materias->listActive(),
            'grupos' => $gestion !== null ? $this->grupos->listByGestion($gestion->id) : [],
            'materiaSel' => $materiaId,
            'grupoSel' => $grupoId,
            'asignados' => $asignados,
            'disponibles' => $disponibles,
        ]);
    }

    #[Route('/grupos/masivo', name: 'nota_grupos_masivo', methods: ['POST'])]
    public function gruposMasivo(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $materiaId = (int) $request->request->get('materia', 0);
        $grupoIds = array_map('intval', (array) $request->request->all('grupos'));

        try {
            $resultado = $this->asignarInscritosMasivo->execute(new AsignarGruposMasivoInput(
                materiaId: $materiaId,
                grupoIds: $grupoIds,
                turnoPreferencia: self::nullableString($request->request->get('turnoPreferencia')),
                reasignarExistentes: $request->request->has('reasignarExistentes'),
                actorUserId: $this->actorUserId($request),
            ));
            $this->addFlash('success', sprintf(
                '%d estudiante(s) asignado(s). %d omitido(s).',
                $resultado['asignados'],
                $resultado['omitidos'],
            ));
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('nota_grupos', [
            'materia' => $materiaId,
            'grupo' => $grupoIds[0] ?? 0,
        ]);
    }

    #[Route('/grupos/{id}/eliminar', name: 'nota_grupo_delete', methods: ['POST'])]
    public function eliminarInscritoGrupo(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $materiaId = (int) $request->request->get('materia', 0);
        $grupoId = (int) $request->request->get('grupo', 0);

        try {
            $this->desasignarInscritoGrupo->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Estudiante quitado del grupo.');
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('nota_grupos', ['materia' => $materiaId, 'grupo' => $grupoId]);
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
            $data = $this->verPlanilla->execute($materiaId, $grupoId);
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
            $data = $this->verPlanilla->execute($materiaId, $grupoId);
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
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
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

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
