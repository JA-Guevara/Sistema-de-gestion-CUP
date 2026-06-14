<?php

declare(strict_types=1);

namespace App\Inscripcion\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\DTO\InscripcionEditInput;
use App\Inscripcion\Application\DTO\InscripcionesBulkInput;
use App\Inscripcion\Application\DTO\RechazarInscripcionesBulkInput;
use App\Inscripcion\Application\UseCase\ActualizarInscripcion;
use App\Inscripcion\Application\UseCase\AgendarRevision;
use App\Inscripcion\Application\UseCase\BuscarPostulante;
use App\Inscripcion\Application\UseCase\CrearInscripcion;
use App\Inscripcion\Application\UseCase\EliminarDocumento;
use App\Inscripcion\Application\UseCase\EliminarInscripcion;
use App\Inscripcion\Application\UseCase\ListInscripciones;
use App\Inscripcion\Application\UseCase\RechazarInscripcion;
use App\Inscripcion\Application\UseCase\RechazarInscripcionesBulk;
use App\Inscripcion\Application\UseCase\RevisarDocumento;
use App\Inscripcion\Application\UseCase\SubirDocumento;
use App\Inscripcion\Application\UseCase\ValidarInscripcion;
use App\Inscripcion\Application\UseCase\ValidarInscripcionesBulk;
use App\Inscripcion\Application\UseCase\VerInscripcion;
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
        private readonly RevisarDocumento $revisarDocumento,
        private readonly UserRepository $users,
        private readonly GestionRepository $gestiones,
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
            'user' => $user,
        ]);
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
            ]);
        }
    }

    #[Route('/{id}/eliminar', name: 'inscripcion_delete', methods: ['POST'])]
    public function eliminar(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
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

        try {
            $this->eliminarDocumento->execute($documentoId, $this->actorUserId($request));
            $this->addFlash('success', 'Documento eliminado correctamente.');
        } catch (DocumentoException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $referer = $request->headers->get('referer');

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
        ]);
    }

    #[Route('/admin/validar', name: 'inscripcion_admin_validar_bulk', methods: ['POST'])]
    public function validarBulk(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
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
                'user' => $this->currentUser($request),
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

        try {
            $this->validarInscripcion->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Documentacion validada. Continua con la confirmacion.');
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

        $motivo = self::nullableString($request->request->get('motivo'));

        try {
            $this->rechazarInscripcion->execute($id, $motivo, $this->actorUserId($request));
            $this->addFlash('success', 'Postulacion rechazada.');
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

        $estado = strtoupper(trim((string) $request->request->get('estado', '')));
        $observacion = self::nullableString($request->request->get('observacion'));
        $inscripcionId = (int) $request->request->get('inscripcionId', 0);

        try {
            $this->revisarDocumento->execute($documentoId, $estado, $observacion, $this->actorUserId($request));
            $this->addFlash('success', 'Documento revisado.');
        } catch (DocumentoException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('inscripcion_admin_detalle', ['id' => $inscripcionId]);
    }

    private function renderFormulario(Request $request, User $user, ?\App\Gestion\Domain\Entity\Gestion $gestion): Response
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

        return $this->render('@inscripcion/formulario.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'carreras' => $carrerasDisponibles,
            'inscripcionAbierta' => $inscripcionAbierta,
        ]);
    }

    private function redirectToGestion(Request $request): RedirectResponse
    {
        $gestionId = (int) $request->request->get('gestion', 0);

        return $gestionId > 0
            ? $this->redirectToRoute('inscripcion_admin', ['gestion' => $gestionId])
            : $this->redirectToRoute('inscripcion_admin');
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
