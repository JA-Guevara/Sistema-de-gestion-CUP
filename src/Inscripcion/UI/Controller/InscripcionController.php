<?php

declare(strict_types=1);

namespace App\Inscripcion\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\DTO\InscripcionEditInput;
use App\Inscripcion\Application\UseCase\ActualizarInscripcion;
use App\Inscripcion\Application\UseCase\BuscarPostulante;
use App\Inscripcion\Application\UseCase\CrearInscripcion;
use App\Inscripcion\Application\UseCase\EliminarDocumento;
use App\Inscripcion\Application\UseCase\EliminarInscripcion;
use App\Inscripcion\Application\UseCase\ListInscripciones;
use App\Inscripcion\Application\UseCase\SubirDocumento;
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

        $inscripcion = $this->verInscripcion->execute($user);

        if ($inscripcion !== null) {
            return $this->render('@inscripcion/detalle.html.twig', [
                'inscripcion' => $inscripcion,
                'user' => $user,
            ]);
        }

        return $this->redirectToRoute('inscripcion_formulario');
    }

    #[Route('/formulario', name: 'inscripcion_formulario', methods: ['GET', 'POST'])]
    public function formulario(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->gestiones->findActive();
        $inscripcionExistente = $gestion !== null
            ? $this->verInscripcion->execute($user)
            : null;

        if ($inscripcionExistente !== null) {
            return $this->redirectToRoute('inscripcion_index');
        }

        if (!$request->isMethod('POST')) {
            return $this->renderFormulario($request, $user, $gestion);
        }

        try {
            $this->crearInscripcion->execute(InscripcionRequest::fromRequest($request), $user);
            $this->addFlash('success', 'Pre-inscripcion registrada correctamente.');

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

        $gestion = $this->gestiones->findActive();
        $inscripciones = $gestion !== null
            ? $this->listInscripciones->execute($gestion->id)
            : [];

        return $this->render('@inscripcion/admin_lista.html.twig', [
            'inscripciones' => $inscripciones,
            'gestion' => $gestion,
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
            'user' => $this->currentUser($request),
        ]);
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
