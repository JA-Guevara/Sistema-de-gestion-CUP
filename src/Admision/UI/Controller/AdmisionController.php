<?php

declare(strict_types=1);

namespace App\Admision\UI\Controller;

use App\Admision\Application\UseCase\EjecutarAdmisionFinal;
use App\Admision\Application\UseCase\VerAdmision;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\UseCase\ResolverResultadoAdmision;
use App\Inscripcion\Domain\Exception\InscripcionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modulo Admision Final: adjudica carrera a los estudiantes que aprobaron el CUP
 * por promedio respetando los cupos por carrera (1ra/2da opcion / lista de espera).
 */
#[Route('/admision')]
final class AdmisionController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const CSRF_INTENTION = 'admision';
    private const CSRF_ERROR = 'La sesion expiro o el formulario no es valido. Vuelve a intentarlo.';

    public function __construct(
        private readonly VerAdmision $verAdmision,
        private readonly EjecutarAdmisionFinal $ejecutarAdmision,
        private readonly ResolverResultadoAdmision $resolverDocente,
        private readonly GestionRepository $gestiones,
        private readonly UserRepository $users,
        private readonly CsrfManager $csrf,
    ) {
    }

    #[Route('', name: 'admision_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestion = $this->gestiones->findActive();
        $data = $gestion !== null ? $this->verAdmision->execute((int) $gestion->id) : null;

        return $this->render('@admision/index.html.twig', [
            'user' => $user,
            'gestion' => $gestion,
            'data' => $data,
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    #[Route('/ejecutar', name: 'admision_ejecutar', methods: ['POST'])]
    public function ejecutar(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->csrf->validate(self::CSRF_INTENTION, (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('admision_index');
        }

        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            $this->addFlash('error', 'No hay una gestion CUP activa.');

            return $this->redirectToRoute('admision_index');
        }

        try {
            $r = $this->ejecutarAdmision->execute((int) $gestion->id, $this->actorUserId($request));
            $this->addFlash('success', sprintf(
                'Admision ejecutada: %d admitidos a 1ra opcion, %d a 2da, %d en lista de espera, %d reprobados, %d pendientes.',
                $r['admitidosPrimera'],
                $r['admitidosSegunda'],
                $r['listaEspera'],
                $r['reprobados'],
                $r['pendientes'],
            ));
        } catch (InscripcionException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admision_index');
    }

    #[Route('/docente/resolver', name: 'admision_docente_resolver', methods: ['POST'])]
    public function resolverDocente(Request $request): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->csrf->validate(self::CSRF_INTENTION, (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('admision_index');
        }

        $inscripcionId = (int) $request->request->get('inscripcion', 0);
        $resultado = (string) $request->request->get('resultado', '');
        $motivo = trim((string) $request->request->get('motivo', ''));

        try {
            $this->resolverDocente->execute($inscripcionId, $resultado, $motivo !== '' ? $motivo : null, $this->actorUserId($request));
            $this->addFlash('success', 'Decision de contratacion del docente registrada.');
        } catch (InscripcionException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admision_index');
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
