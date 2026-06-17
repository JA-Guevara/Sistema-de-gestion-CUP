<?php

declare(strict_types=1);

namespace App\Inscripcion\UI\Controller;

use App\Academico\Materia\Domain\Catalog\AreaCatalog;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Inscripcion\Infrastructure\Persistence\CalendarioRevisionRepository;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\DTO\ActaRecepcionInput;
use App\Inscripcion\Application\DTO\InscripcionEditInput;
use App\Inscripcion\Application\DTO\InscripcionesBulkInput;
use App\Inscripcion\Application\DTO\RechazarInscripcionesBulkInput;
use App\Inscripcion\Application\UseCase\ActualizarInscripcion;
use App\Inscripcion\Application\UseCase\AgendarEntrevista;
use App\Inscripcion\Application\UseCase\AgendarRevision;
use App\Inscripcion\Application\UseCase\AgregarDiaRevision;
use App\Inscripcion\Application\UseCase\EliminarDiaRevision;
use App\Inscripcion\Application\UseCase\GenerarDiasRevision;
use App\Inscripcion\Application\UseCase\ToggleDiaRevision;
use App\Inscripcion\Application\UseCase\AnularInscripcion;
use App\Inscripcion\Application\UseCase\BuscarPostulante;
use App\Inscripcion\Application\UseCase\EditarBorrador;
use App\Inscripcion\Application\UseCase\RechazarSolicitudAnulacion;
use App\Inscripcion\Application\UseCase\SolicitarAnulacion;
use App\Inscripcion\Application\UseCase\ConfirmarInscripcion;
use App\Inscripcion\Application\UseCase\CrearInscripcion;
use App\Inscripcion\Application\UseCase\EliminarDocumento;
use App\Inscripcion\Application\UseCase\EliminarInscripcion;
use App\Inscripcion\Application\UseCase\GuardarActaRecepcion;
use App\Inscripcion\Application\UseCase\ListInscripciones;
use App\Inscripcion\Application\UseCase\PagarInscripcion;
use App\Inscripcion\Application\UseCase\PresentarInscripcion;
use App\Inscripcion\Application\UseCase\RechazarInscripcion;
use App\Inscripcion\Application\UseCase\RechazarInscripcionesBulk;
use App\Inscripcion\Application\UseCase\ResolverResultadoAdmision;
use App\Inscripcion\Application\UseCase\RevisarDocumento;
use App\Inscripcion\Application\UseCase\SubirDocumento;
use App\Inscripcion\Application\UseCase\ValidarInscripcion;
use App\Inscripcion\Application\UseCase\ValidarInscripcionesBulk;
use App\Inscripcion\Application\UseCase\VerInscripcion;
use App\Inscripcion\Domain\Catalog\RequisitoCatalog;
use App\Inscripcion\Domain\Exception\DocumentoException;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\UI\Request\InscripcionRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inscripcion')]
final class InscripcionController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const CSRF_INTENTION = 'inscripcion';
    private const CSRF_ERROR = 'La sesion expiro o el formulario no es valido. Vuelve a intentarlo.';

    public function __construct(
        private readonly CrearInscripcion $crearInscripcion,
        private readonly ActualizarInscripcion $actualizarInscripcion,
        private readonly EliminarInscripcion $eliminarInscripcion,
        private readonly ListInscripciones $listInscripciones,
        private readonly VerInscripcion $verInscripcion,
        private readonly BuscarPostulante $buscarPostulante,
        private readonly SubirDocumento $subirDocumento,
        private readonly EliminarDocumento $eliminarDocumento,
        private readonly AgendarRevision $agendarRevision,
        private readonly ValidarInscripcion $validarInscripcion,
        private readonly RechazarInscripcion $rechazarInscripcion,
        private readonly ValidarInscripcionesBulk $validarInscripcionesBulk,
        private readonly RechazarInscripcionesBulk $rechazarInscripcionesBulk,
        private readonly AgendarEntrevista $agendarEntrevista,
        private readonly ConfirmarInscripcion $confirmarInscripcion,
        private readonly ResolverResultadoAdmision $resolverResultadoAdmision,
        private readonly PagarInscripcion $pagarInscripcion,
        private readonly PresentarInscripcion $presentarInscripcion,
        private readonly EditarBorrador $editarBorrador,
        private readonly AnularInscripcion $anularInscripcion,
        private readonly SolicitarAnulacion $solicitarAnulacion,
        private readonly RechazarSolicitudAnulacion $rechazarSolicitudAnulacion,
        private readonly RevisarDocumento $revisarDocumento,
        private readonly GuardarActaRecepcion $guardarActaRecepcion,
        private readonly AgregarDiaRevision $agregarDiaRevision,
        private readonly GenerarDiasRevision $generarDiasRevision,
        private readonly EliminarDiaRevision $eliminarDiaRevision,
        private readonly ToggleDiaRevision $toggleDiaRevision,
        private readonly CalendarioRevisionRepository $calendarioRevision,
        private readonly UserRepository $users,
        private readonly GestionRepository $gestiones,
        private readonly CsrfManager $csrf,
    ) {
    }

    #[Route('', name: 'inscripcion_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        return $this->render('@inscripcion/mis_postulaciones.html.twig', [
            'user' => $user,
            'postulaciones' => $this->verInscripcion->listByUser($user),
            'gestion' => $this->gestiones->findActive(),
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/ver/{id}', name: 'inscripcion_ver', methods: ['GET'])]
    public function ver(Request $request, int $id): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('inscripcion_index');
        }

        if ($inscripcion->user->id !== $user->id) {
            $this->addFlash('error', 'No puedes ver esa postulacion.');

            return $this->redirectToRoute('inscripcion_index');
        }

        return $this->render('@inscripcion/detalle.html.twig', [
            'inscripcion' => $inscripcion,
            'requisitos' => RequisitoCatalog::paraTipo($inscripcion->tipo),
            'user' => $user,
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/pagar/{id}', name: 'inscripcion_pagar', methods: ['POST'])]
    public function pagar(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        try {
            $this->pagarInscripcion->execute($id, (int) $user->id);
            $this->addFlash('success', 'Pago registrado. Tu inscripcion fue confirmada: ya eres estudiante del CUP.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
    }

    #[Route('/presentar/{id}', name: 'inscripcion_presentar', methods: ['POST'])]
    public function presentar(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        try {
            $cita = $this->presentarInscripcion->execute($id, (int) $user->id);
            $this->addFlash('success', $cita !== null
                ? sprintf('Pre-inscripcion presentada. Tu cita de revision de documentos es el %s.', $cita->format('d/m/Y H:i'))
                : 'Pre-inscripcion presentada. Te asignaremos fecha para la revision de documentos.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
    }

    #[Route('/borrador/{id}/eliminar', name: 'inscripcion_borrador_eliminar', methods: ['POST'])]
    public function eliminarBorrador(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_index');
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);
            if ($inscripcion->user->id !== $user->id) {
                throw new InscripcionException('No puedes descartar una postulacion que no es tuya.');
            }
            if (!$inscripcion->isBorrador()) {
                throw new InscripcionException('Solo puedes descartar un borrador.');
            }
            $this->eliminarInscripcion->execute($id, (int) $user->id);
            $this->addFlash('success', 'Borrador descartado.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_index');
    }

    #[Route('/borrador/{id}/editar', name: 'inscripcion_borrador_editar', methods: ['GET', 'POST'])]
    public function editarBorrador(Request $request, int $id): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('inscripcion_index');
        }

        if ($inscripcion->user->id !== $user->id) {
            $this->addFlash('error', 'No puedes editar esa postulacion.');

            return $this->redirectToRoute('inscripcion_index');
        }

        if (!$inscripcion->puedeEditarse()) {
            $this->addFlash('error', 'Solo puedes editar un borrador.');

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        $gestion = $this->gestiones->findActive();

        if (!$request->isMethod('POST')) {
            return $this->renderFormulario($request, $user, $gestion, $inscripcion);
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->renderFormulario($request, $user, $gestion, $inscripcion);
        }

        try {
            $this->editarBorrador->execute($id, InscripcionRequest::fromRequest($request), (int) $user->id);
            $this->addFlash('success', 'Borrador actualizado.');

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderFormulario($request, $user, $gestion, $inscripcion);
        }
    }

    #[Route('/anular/{id}', name: 'inscripcion_anular', methods: ['POST'])]
    public function anular(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);
            if ($inscripcion->user->id !== $user->id) {
                throw new InscripcionException('No puedes anular una postulacion que no es tuya.');
            }
            if (!$inscripcion->isPresentada()) {
                throw new InscripcionException('Solo puedes anular directamente una postulacion presentada. Si ya fue validada o confirmada, solicita la anulacion.');
            }
            $this->anularInscripcion->execute($id, (int) $user->id);
            $this->addFlash('success', 'Postulacion anulada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
    }

    #[Route('/solicitar-anulacion/{id}', name: 'inscripcion_solicitar_anulacion', methods: ['POST'])]
    public function solicitarAnulacion(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        $motivo = self::nullableString($request->request->get('motivo'));

        try {
            $this->solicitarAnulacion->execute($id, (int) $user->id, $motivo);
            $this->addFlash('success', 'Solicitud de anulacion enviada. La administracion la revisara.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
    }

    #[Route('/formulario', name: 'inscripcion_formulario', methods: ['GET', 'POST'])]
    public function formulario(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->gestiones->findActive();

        if (!$request->isMethod('POST')) {
            return $this->renderFormulario($request, $user, $gestion);
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->renderFormulario($request, $user, $gestion);
        }

        try {
            $inscripcion = $this->crearInscripcion->execute(InscripcionRequest::fromRequest($request), $user);
            $this->addFlash('success', $inscripcion->isBorrador()
                ? 'Borrador guardado. Completa y presenta tu pre-inscripcion cuando estes listo.'
                : 'Pre-inscripcion presentada. Te asignaremos fecha para la validacion de documentos.');

            return $this->redirectToRoute('inscripcion_index');
        } catch (InscripcionException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->renderFormulario($request, $user, $gestion);
        }
    }

    #[Route('/{id}/editar', name: 'inscripcion_edit', methods: ['GET', 'POST'])]
    public function editar(Request $request, int $id): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('inscripcion_admin');
        }

        $gestion = $this->gestiones->findActive();
        $carrerasDisponibles = [];
        if ($gestion !== null) {
            foreach ($gestion->carreras as $cg) {
                if ($cg->habilitada) {
                    $carrerasDisponibles[] = $cg->carrera;
                }
            }
        }

        if (!$request->isMethod('POST')) {
            return $this->render('@inscripcion/edit.html.twig', [
                'inscripcion' => $inscripcion,
                'carreras' => $carrerasDisponibles,
                'user' => $user,
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->render('@inscripcion/edit.html.twig', [
                'inscripcion' => $inscripcion,
                'carreras' => $carrerasDisponibles,
                'user' => $user,
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        }

        try {
            $this->actualizarInscripcion->execute(new InscripcionEditInput(
                inscripcionId: $id,
                nombres: trim((string) $request->request->get('nombres', '')),
                apellidos: trim((string) $request->request->get('apellidos', '')),
                direccion: self::nullableString($request->request->get('direccion')),
                telefono: self::nullableString($request->request->get('telefono')),
                email: trim((string) $request->request->get('email', '')),
                colegioProcedencia: self::nullableString($request->request->get('colegioProcedencia')),
                ciudad: self::nullableString($request->request->get('ciudad')),
                tituloBachiller: $request->request->has('tituloBachiller'),
                turnoPreferencia: self::nullableString($request->request->get('turnoPreferencia')),
                otros: self::nullableString($request->request->get('otros')),
                carreraId: (int) $request->request->get('carrera', 0),
                actorUserId: $this->actorUserId($request),
            ));
            $this->addFlash('success', 'Inscripcion actualizada correctamente.');

            return $this->redirectToRoute('inscripcion_admin');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->render('@inscripcion/edit.html.twig', [
                'inscripcion' => $inscripcion,
                'carreras' => $carrerasDisponibles,
                'user' => $user,
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        }
    }

    #[Route('/{id}/eliminar', name: 'inscripcion_delete', methods: ['POST'])]
    public function eliminar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin');
        }

        try {
            $this->eliminarInscripcion->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Inscripcion eliminada correctamente.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin');
    }

    #[Route('/{id}/documentos/subir', name: 'inscripcion_documento_subir', methods: ['POST'])]
    public function subirDocumento(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);
            $file = $request->files->get('archivo');
            $nombre = (string) $request->request->get('nombreDocumento', 'Documento');

            if ($file === null || !$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                throw new DocumentoException('No se recibio ningun archivo.');
            }

            $this->subirDocumento->execute($inscripcion, $file, $nombre, $this->actorUserId($request));
            $this->addFlash('success', 'Documento subido correctamente.');
        } catch (DocumentoException | InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/documentos/{documentoId}/eliminar', name: 'inscripcion_documento_eliminar', methods: ['POST'])]
    public function eliminarDocumento(Request $request, int $documentoId): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $referer = $request->headers->get('referer');

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $referer !== null
                ? $this->redirect($referer)
                : $this->redirectToRoute('inscripcion_admin');
        }

        try {
            $this->eliminarDocumento->execute($documentoId, $this->actorUserId($request));
            $this->addFlash('success', 'Documento eliminado correctamente.');
        } catch (DocumentoException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $referer !== null
            ? $this->redirect($referer)
            : $this->redirectToRoute('inscripcion_admin');
    }

    #[Route('/admin', name: 'inscripcion_admin', methods: ['GET'])]
    public function adminList(Request $request): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestiones = $this->gestiones->listAll();

        // La gestion seleccionada viene por query (?gestion=ID); por defecto, la activa.
        $gestionIdParam = $request->query->getInt('gestion', 0);
        $gestion = $gestionIdParam > 0
            ? $this->gestiones->findById($gestionIdParam)
            : $this->gestiones->findActive();

        $inscripciones = $gestion !== null
            ? $this->listInscripciones->execute($gestion->id)
            : [];

        return $this->render('@inscripcion/admin_lista.html.twig', [
            'inscripciones' => $inscripciones,
            'gestion' => $gestion,
            'gestiones' => $gestiones,
            'user' => $this->currentUser($request),
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/admin/buscar', name: 'inscripcion_buscar', methods: ['GET'])]
    public function buscar(Request $request): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $term = trim((string) $request->query->get('q', ''));
        $resultados = $term !== ''
            ? $this->buscarPostulante->execute($term)
            : [];

        return $this->render('@inscripcion/admin_lista.html.twig', [
            'inscripciones' => $resultados,
            'searchTerm' => $term,
            'gestion' => $this->gestiones->findActive(),
            'gestiones' => $this->gestiones->listAll(),
            'user' => $this->currentUser($request),
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/admin/validar', name: 'inscripcion_admin_validar_bulk', methods: ['POST'])]
    public function validarBulk(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToGestion($request);
        }

        $ids = array_map('intval', (array) $request->request->all('inscripciones'));

        try {
            $resultado = $this->validarInscripcionesBulk->execute(
                new InscripcionesBulkInput($ids, $this->actorUserId($request)),
            );
            $mensaje = sprintf('%d postulacion(es) aprobada(s).', $resultado['validadas']);
            if ($resultado['omitidas'] > 0) {
                $mensaje .= sprintf(' %d omitida(s) por documentos pendientes o estado no valido.', $resultado['omitidas']);
            }
            $this->addFlash($resultado['validadas'] > 0 ? 'success' : 'error', $mensaje);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToGestion($request);
    }

    #[Route('/admin/rechazar', name: 'inscripcion_admin_rechazar_bulk', methods: ['POST'])]
    public function rechazarBulk(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToGestion($request);
        }

        $ids = array_map('intval', (array) $request->request->all('inscripciones'));
        $motivo = self::nullableString($request->request->get('motivo'));

        try {
            $total = $this->rechazarInscripcionesBulk->execute(
                new RechazarInscripcionesBulkInput($ids, $motivo, $this->actorUserId($request)),
            );
            $this->addFlash($total > 0 ? 'success' : 'error', sprintf('%d postulacion(es) rechazada(s).', $total));
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToGestion($request);
    }

    #[Route('/admin/calendario', name: 'inscripcion_admin_calendario', methods: ['GET'])]
    public function calendario(Request $request): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestiones = $this->gestiones->listAll();
        $gestionIdParam = $request->query->getInt('gestion', 0);
        $gestion = $gestionIdParam > 0
            ? $this->gestiones->findById($gestionIdParam)
            : $this->gestiones->findActive();

        $dias = $gestion !== null
            ? $this->calendarioRevision->listByGestion((int) $gestion->id)
            : [];

        return $this->render('@inscripcion/calendario.html.twig', [
            'gestion' => $gestion,
            'gestiones' => $gestiones,
            'dias' => $dias,
            'user' => $this->currentUser($request),
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/admin/calendario/agregar', name: 'inscripcion_admin_calendario_agregar', methods: ['POST'])]
    public function calendarioAgregar(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToCalendario($request);
        }

        $gestionId = (int) $request->request->get('gestion', 0);
        $fechaStr = trim((string) $request->request->get('fecha', ''));
        $capacidad = (int) $request->request->get('capacidad', 0);

        if ($fechaStr === '') {
            $this->addFlash('error', 'Indica la fecha del dia de revision.');

            return $this->redirectToCalendario($request);
        }

        try {
            $this->agregarDiaRevision->execute($gestionId, new \DateTimeImmutable($fechaStr), $capacidad, $this->actorUserId($request));
            $this->addFlash('success', 'Dia de revision agregado.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Exception) {
            $this->addFlash('error', 'La fecha indicada no es valida.');
        }

        return $this->redirectToCalendario($request);
    }

    #[Route('/admin/calendario/generar', name: 'inscripcion_admin_calendario_generar', methods: ['POST'])]
    public function calendarioGenerar(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToCalendario($request);
        }

        $gestionId = (int) $request->request->get('gestion', 0);
        $desdeStr = trim((string) $request->request->get('desde', ''));
        $hastaStr = trim((string) $request->request->get('hasta', ''));
        $capacidad = (int) $request->request->get('capacidad', 0);
        $soloLaborables = $request->request->has('soloLaborables');

        if ($desdeStr === '' || $hastaStr === '') {
            $this->addFlash('error', 'Indica la fecha inicial y final del rango.');

            return $this->redirectToCalendario($request);
        }

        try {
            $creados = $this->generarDiasRevision->execute(
                $gestionId,
                new \DateTimeImmutable($desdeStr),
                new \DateTimeImmutable($hastaStr),
                $capacidad,
                $soloLaborables,
                $this->actorUserId($request),
            );
            $this->addFlash($creados > 0 ? 'success' : 'error', $creados > 0
                ? sprintf('%d dia(s) de revision generado(s).', $creados)
                : 'No se generaron dias (ya existian o el rango no tiene dias validos).');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Exception) {
            $this->addFlash('error', 'Las fechas indicadas no son validas.');
        }

        return $this->redirectToCalendario($request);
    }

    #[Route('/admin/calendario/{id}/eliminar', name: 'inscripcion_admin_calendario_eliminar', methods: ['POST'])]
    public function calendarioEliminar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToCalendario($request);
        }

        try {
            $this->eliminarDiaRevision->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Dia de revision eliminado.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToCalendario($request);
    }

    #[Route('/admin/calendario/{id}/toggle', name: 'inscripcion_admin_calendario_toggle', methods: ['POST'])]
    public function calendarioToggle(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToCalendario($request);
        }

        try {
            $this->toggleDiaRevision->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Dia de revision actualizado.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToCalendario($request);
    }

    #[Route('/admin/{id}', name: 'inscripcion_admin_detalle', methods: ['GET'])]
    public function adminDetalle(Request $request, int $id): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $inscripcion = $this->verInscripcion->executeById($id);

            return $this->render('@inscripcion/admin_detalle.html.twig', [
                'inscripcion' => $inscripcion,
                'requisitos' => RequisitoCatalog::paraTipo($inscripcion->tipo),
                'user' => $this->currentUser($request),
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('inscripcion_admin');
        }
    }

    #[Route('/admin/{id}/agendar', name: 'inscripcion_admin_agendar', methods: ['POST'])]
    public function agendar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        $fechaStr = trim((string) $request->request->get('fecha', ''));
        if ($fechaStr === '') {
            $this->addFlash('error', 'Indica una fecha y hora para la revision.');

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $this->agendarRevision->execute($id, new \DateTimeImmutable($fechaStr), $this->actorUserId($request));
            $this->addFlash('success', 'Revision de documentos agendada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Exception) {
            $this->addFlash('error', 'La fecha indicada no es valida.');
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/validar', name: 'inscripcion_admin_validar', methods: ['POST'])]
    public function validar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $this->validarInscripcion->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Documentacion aprobada. Continua con el siguiente paso de admision.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/rechazar', name: 'inscripcion_admin_rechazar', methods: ['POST'])]
    public function rechazar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        $motivo = self::nullableString($request->request->get('motivo'));

        try {
            $this->rechazarInscripcion->execute($id, $motivo, $this->actorUserId($request));
            $this->addFlash('success', 'Postulacion descartada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/entrevista', name: 'inscripcion_admin_entrevista', methods: ['POST'])]
    public function entrevista(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        $fechaStr = trim((string) $request->request->get('fecha', ''));
        if ($fechaStr === '') {
            $this->addFlash('error', 'Indica una fecha y hora para la entrevista.');

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $this->agendarEntrevista->execute($id, new \DateTimeImmutable($fechaStr), $this->actorUserId($request));
            $this->addFlash('success', 'Entrevista agendada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Exception) {
            $this->addFlash('error', 'La fecha indicada no es valida.');
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/resolver', name: 'inscripcion_admin_resolver', methods: ['POST'])]
    public function resolver(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        $resultado = strtoupper(trim((string) $request->request->get('resultado', '')));
        $motivo = self::nullableString($request->request->get('motivo'));

        try {
            $this->resolverResultadoAdmision->execute($id, $resultado, $motivo, $this->actorUserId($request));
            $this->addFlash('success', $resultado === 'APROBADO' ? 'Postulacion aprobada y rol asignado.' : 'Postulacion descartada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/confirmar', name: 'inscripcion_admin_confirmar', methods: ['POST'])]
    public function confirmar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $this->confirmarInscripcion->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Postulacion confirmada y rol asignado.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/anular', name: 'inscripcion_admin_anular', methods: ['POST'])]
    public function adminAnular(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $this->anularInscripcion->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Postulacion anulada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/anulacion-rechazar', name: 'inscripcion_admin_anulacion_rechazar', methods: ['POST'])]
    public function adminRechazarAnulacion(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        try {
            $this->rechazarSolicitudAnulacion->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Solicitud de anulacion descartada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/{id}/acta', name: 'inscripcion_admin_acta', methods: ['POST'])]
    public function guardarActa(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
        }

        /** @var array<string,string> $estados */
        $estados = array_map(static fn ($v): string => (string) $v, (array) $request->request->all('estado'));
        /** @var array<string,?string> $observaciones */
        $observaciones = array_map([self::class, 'nullableString'], (array) $request->request->all('observacion'));

        try {
            $this->guardarActaRecepcion->execute(new ActaRecepcionInput($id, $estados, $observaciones, $this->actorUserId($request)));
            $this->addFlash('success', 'Acta de recepcion guardada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $id]);
    }

    #[Route('/admin/documentos/{documentoId}/revisar', name: 'inscripcion_admin_documento_revisar', methods: ['POST'])]
    public function revisarDocumento(Request $request, int $documentoId): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $inscripcionId = (int) $request->request->get('inscripcionId', 0);

        if (!$this->isCsrfValid($request)) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $inscripcionId]);
        }

        $estado = strtoupper(trim((string) $request->request->get('estado', '')));
        $observacion = self::nullableString($request->request->get('observacion'));

        try {
            $this->revisarDocumento->execute($documentoId, $estado, $observacion, $this->actorUserId($request));
            $this->addFlash('success', 'Documento revisado.');
        } catch (DocumentoException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $inscripcionId]);
    }

    private function renderFormulario(Request $request, User $user, ?\App\Gestion\Domain\Entity\Gestion $gestion, ?\App\Inscripcion\Domain\Entity\Inscripcion $inscripcion = null): Response
    {
        $carrerasDisponibles = [];
        $inscripcionAbierta = false;

        if ($gestion !== null) {
            $inscripcionAbierta = $gestion->estado === EstadoGestion::ABIERTA_INSCRIPCION;
            foreach ($gestion->carreras as $cg) {
                if ($cg->habilitada) {
                    $carrerasDisponibles[] = $cg->carrera;
                }
            }
        }

        // En modo edicion de un borrador siempre se muestra el formulario.
        if ($inscripcion !== null) {
            $inscripcionAbierta = true;
        }

        return $this->render('@inscripcion/formulario.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'carreras' => $carrerasDisponibles,
            'inscripcionAbierta' => $inscripcionAbierta,
            'inscripcion' => $inscripcion,
            'areas' => AreaCatalog::all(),
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    private function redirectToGestion(Request $request): RedirectResponse
    {
        $gestionId = (int) $request->request->get('gestion', 0);

        return $gestionId > 0
            ? $this->redirectToRoute('inscripcion_admin', ['gestion' => $gestionId])
            : $this->redirectToRoute('inscripcion_admin');
    }

    private function redirectToCalendario(Request $request): RedirectResponse
    {
        $gestionId = (int) $request->request->get('gestion', 0);

        return $gestionId > 0
            ? $this->redirectToRoute('inscripcion_admin_calendario', ['gestion' => $gestionId])
            : $this->redirectToRoute('inscripcion_admin_calendario');
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Valida el token CSRF enviado por el formulario contra el emitido en sesion.
     * Mismo patron que el modulo Auth (CsrfManager, uso unico por intencion).
     */
    private function isCsrfValid(Request $request): bool
    {
        return $this->csrf->validate(
            self::CSRF_INTENTION,
            (string) $request->request->get('_csrf_token', ''),
        );
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
