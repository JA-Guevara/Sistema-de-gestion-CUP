<?php

declare(strict_types=1);

namespace App\Asignacion\UI\Controller;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Academico\Turno\Infrastructure\Persistence\TurnoRepository;
use App\Asignacion\Application\UseCase\AsignarDocenteAGrupoMaterias;
use App\Asignacion\Application\UseCase\AsignarEstudianteAGrupo;
use App\Asignacion\Application\UseCase\DesasignarDocenteDeGrupo;
use App\Asignacion\Application\UseCase\QuitarEstudianteDeGrupo;
use App\Asignacion\Application\UseCase\ShowAsignacionDashboard;
use App\Asignacion\Application\UseCase\ShowGrupoDetalle;
use App\Asignacion\UI\Request\AsignarDocenteGrupoMateriasRequest;
use App\Asignacion\UI\Request\AsignarEstudianteGrupoRequest;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modulo de Asignaciones centrado en GRUPOS: panel de tarjetas de grupo,
 * asignacion de estudiantes a grupos completos y de docentes a grupos
 * (individual y masiva).
 */
#[Route('/asignaciones')]
final class AsignacionController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const CSRF_INTENTION = 'asignacion';
    private const CSRF_ERROR = 'La sesion expiro o el formulario no es valido. Vuelve a intentarlo.';
    private const MAX_GRUPOS_DOCENTE = 4;

    public function __construct(
        private readonly ShowAsignacionDashboard $showDashboard,
        private readonly ShowGrupoDetalle $showGrupoDetalle,
        private readonly AsignarEstudianteAGrupo $asignarEstudiante,
        private readonly QuitarEstudianteDeGrupo $quitarEstudiante,
        private readonly AsignarDocenteAGrupoMaterias $asignarDocenteMaterias,
        private readonly DesasignarDocenteDeGrupo $desasignarDocente,
        private readonly UserRepository $users,
        private readonly GestionRepository $gestiones,
        private readonly GrupoRepository $grupos,
        private readonly MateriaRepository $materias,
        private readonly TurnoRepository $turnos,
        private readonly InscripcionRepository $inscripciones,
        private readonly AsignacionDocenteRepository $asignacionesDocente,
        private readonly AsignacionGrupoRepository $asignacionesGrupo,
        private readonly CsrfManager $csrf,
    ) {
    }

    #[Route('', name: 'asignacion_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@asignacion/index.html.twig', array_merge(
            $this->showDashboard->execute(),
            ['user' => $user],
        ));
    }

    #[Route('/grupo/{id}', name: 'asignacion_grupo_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function verGrupo(Request $request, int $id): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $detalle = $this->showGrupoDetalle->execute($id);
        if ($detalle === null) {
            $this->addFlash('error', 'El grupo no existe o no pertenece a la gestion activa.');

            return $this->redirectToRoute('asignacion_index');
        }

        return $this->render('@asignacion/grupo_show.html.twig', array_merge($detalle, ['user' => $user]));
    }

    // ====================== ESTUDIANTES ======================

    #[Route('/estudiantes', name: 'asignacion_estudiantes', methods: ['GET', 'POST'])]
    public function estudiantes(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if ($request->isMethod('POST')) {
            $grupoId = (int) $request->request->get('grupo', 0);
            if (!$this->csrfValido($request)) {
                return $this->redirectToRoute('asignacion_estudiantes', ['grupo' => $grupoId]);
            }

            try {
                $resultado = $this->asignarEstudiante->execute(AsignarEstudianteGrupoRequest::fromRequest($request));
                $this->addFlash('success', sprintf(
                    '%d estudiante(s) asignado(s) al grupo. %d omitido(s).',
                    $resultado['asignados'],
                    $resultado['omitidos'],
                ));
            } catch (NotaException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('asignacion_estudiantes', ['grupo' => $grupoId]);
        }

        $gestion = $this->gestiones->findActive();
        $grupoId = (int) $request->query->get('grupo', 0);

        $detalle = null;
        $asignados = [];
        $disponibles = [];
        if ($gestion !== null && $grupoId > 0) {
            $detalle = $this->showGrupoDetalle->execute($grupoId);
            if ($detalle !== null) {
                $asignados = $detalle['roster'];

                $enGrupo = [];
                foreach ($asignados as $inscripcion) {
                    $enGrupo[(int) $inscripcion->id] = true;
                }

                $grupoActual = $this->asignacionesGrupo->mapGrupoActualByInscripcion((int) $gestion->id);
                $candidatos = $this->inscripciones->listByGestionTipoEstado((int) $gestion->id, TipoPostulacion::ESTUDIANTE, EstadoInscripcion::CONFIRMADA);
                foreach ($candidatos as $inscripcion) {
                    if (isset($enGrupo[(int) $inscripcion->id])) {
                        continue;
                    }
                    $disponibles[] = [
                        'inscripcion' => $inscripcion,
                        'grupoActual' => $grupoActual[(int) $inscripcion->id] ?? null,
                    ];
                }
            }
        }

        return $this->render('@asignacion/estudiantes.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'grupos' => $gestion !== null ? $this->grupos->listByGestion((int) $gestion->id) : [],
            'turnos' => $this->turnos->listActive(),
            'grupoSel' => $grupoId,
            'detalle' => $detalle,
            'asignados' => $asignados,
            'disponibles' => $disponibles,
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/estudiantes/quitar', name: 'asignacion_estudiante_quitar', methods: ['POST'])]
    public function quitarEstudiante(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $grupoId = (int) $request->request->get('grupo', 0);
        if (!$this->csrfValido($request)) {
            return $this->redirectToRoute('asignacion_estudiantes', ['grupo' => $grupoId]);
        }

        $inscripcionId = (int) $request->request->get('inscripcion', 0);

        try {
            $this->quitarEstudiante->execute($inscripcionId, $grupoId, $this->actorUserId($request));
            $this->addFlash('success', 'Estudiante quitado del grupo.');
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('asignacion_estudiantes', ['grupo' => $grupoId]);
    }

    // ====================== DOCENTES ======================

    #[Route('/docentes', name: 'asignacion_docentes', methods: ['GET', 'POST'])]
    public function docentes(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if ($request->isMethod('POST')) {
            $docenteId = (int) $request->request->get('docente', 0);
            $grupoId = (int) $request->request->get('grupo', 0);
            if (!$this->csrfValido($request)) {
                return $this->redirectToRoute('asignacion_docentes', ['docente' => $docenteId, 'grupo' => $grupoId]);
            }

            try {
                $resultado = $this->asignarDocenteMaterias->execute(AsignarDocenteGrupoMateriasRequest::fromRequest($request));
                if ($resultado['asignados'] > 0) {
                    $this->addFlash('success', sprintf(
                        '%d materia(s) asignada(s) al docente en el grupo.%s',
                        $resultado['asignados'],
                        $resultado['omitidos'] > 0 ? sprintf(' %d omitida(s) (ya asignadas, limite de grupos o choque de horario).', $resultado['omitidos']) : '',
                    ));
                } else {
                    $this->addFlash('error', sprintf(
                        'No se asigno ninguna materia. %d omitida(s) por estar ya asignadas, el limite de grupos o choque de horario.',
                        $resultado['omitidos'],
                    ));
                }
            } catch (NotaException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('asignacion_docentes', ['docente' => $docenteId, 'grupo' => $grupoId]);
        }

        $gestion = $this->gestiones->findActive();
        $docenteId = (int) $request->query->get('docente', 0);
        $grupoSel = (int) $request->query->get('grupo', 0);

        $docenteSel = null;
        $asignacionesDocente = [];
        $gruposDelDocente = 0;
        $limiteAlcanzado = false;
        if ($gestion !== null && $docenteId > 0) {
            $docenteSel = $this->users->findById($docenteId);
            $asignacionesDocente = $this->asignacionesDocente->listByDocenteAndGestion($docenteId, (int) $gestion->id);
            $gruposDelDocente = count($this->asignacionesDocente->grupoIdsByDocente($docenteId, (int) $gestion->id));
            $limiteAlcanzado = $gruposDelDocente >= self::MAX_GRUPOS_DOCENTE;
        }

        return $this->render('@asignacion/docentes.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'docentes' => $this->asignacionesDocente->listDocentes(),
            'materias' => $this->materias->listActive(),
            'grupos' => $gestion !== null ? $this->grupos->listByGestion((int) $gestion->id) : [],
            'turnos' => $this->turnos->listActive(),
            'docenteSel' => $docenteSel,
            'grupoSel' => $grupoSel,
            'asignacionesDocente' => $asignacionesDocente,
            'gruposDelDocente' => $gruposDelDocente,
            'maxGrupos' => self::MAX_GRUPOS_DOCENTE,
            'limiteAlcanzado' => $limiteAlcanzado,
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/docentes/quitar/{id}', name: 'asignacion_docente_quitar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function quitarDocente(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $docenteId = (int) $request->request->get('docente', 0);
        if (!$this->csrfValido($request)) {
            return $this->redirectToRoute('asignacion_docentes', ['docente' => $docenteId]);
        }

        try {
            $this->desasignarDocente->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Asignacion de docente eliminada.');
        } catch (NotaException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('asignacion_docentes', ['docente' => $docenteId]);
    }

    // ====================== Helpers ======================

    private function csrfValido(Request $request): bool
    {
        if ($this->csrf->validate(self::CSRF_INTENTION, (string) $request->request->get('_csrf_token', ''))) {
            return true;
        }

        $this->addFlash('error', self::CSRF_ERROR);

        return false;
    }

    private function currentUser(Request $request): ?User
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $this->users->findById($userId) : null;
    }

    private function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);

        return is_int($userId) ? $userId : null;
    }
}
