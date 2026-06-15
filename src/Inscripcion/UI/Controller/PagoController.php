<?php

declare(strict_types=1);

namespace App\Inscripcion\UI\Controller;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Application\UseCase\ActualizarEstadoPagoStripe;
use App\Inscripcion\Application\UseCase\ConfirmarPagoStripe;
use App\Inscripcion\Application\UseCase\IniciarPagoInscripcion;
use App\Inscripcion\Application\UseCase\RegistrarPagoManual;
use App\Inscripcion\Domain\Catalog\EstadoPago;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Payment\StripeGateway;
use App\Inscripcion\Infrastructure\Persistence\PagoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/inscripcion')]
final class PagoController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const CSRF_INTENTION = 'inscripcion';
    private const CSRF_ERROR = 'La sesion expiro o el formulario no es valido. Vuelve a intentarlo.';

    public function __construct(
        private readonly IniciarPagoInscripcion $iniciarPago,
        private readonly ActualizarEstadoPagoStripe $actualizarEstadoPago,
        private readonly ConfirmarPagoStripe $confirmarPago,
        private readonly RegistrarPagoManual $registrarPagoManual,
        private readonly PagoRepository $pagos,
        private readonly GestionRepository $gestiones,
        private readonly StripeGateway $stripe,
        private readonly UserRepository $users,
        private readonly CsrfManager $csrf,
    ) {
    }

    /** Inicia el pago: crea la sesion de Checkout y redirige a Stripe. */
    #[Route('/pago/iniciar/{id}', name: 'inscripcion_pago_iniciar', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function iniciar(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->csrf->validate(self::CSRF_INTENTION, (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        $successUrl = $this->generateUrl('inscripcion_pago_exito', [], UrlGeneratorInterface::ABSOLUTE_URL)
            . '?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->generateUrl('inscripcion_pago_cancelado', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL)
            . '?session_id={CHECKOUT_SESSION_ID}';

        try {
            $url = $this->iniciarPago->execute($id, (int) $user->id, $successUrl, $cancelUrl);
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
        }

        return $this->redirect($url);
    }

    /** URL de retorno tras pagar en Stripe. Confirma (idempotente) y muestra el resultado. */
    #[Route('/pago/exito', name: 'inscripcion_pago_exito', methods: ['GET'])]
    public function exito(Request $request): Response
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $sessionId = trim((string) $request->query->get('session_id', ''));
        $pago = null;
        if ($sessionId !== '') {
            try {
                $pago = $this->confirmarPago->porSession($sessionId);
            } catch (\Throwable) {
                $pago = null; // No rompemos la pagina de retorno por un fallo de verificacion.
            }
        }

        return $this->render('@inscripcion/pago_exito.html.twig', [
            'user' => $user,
            'pago' => $pago,
            'pagado' => $pago !== null && $pago->isPagado(),
        ]);
    }

    /** El postulante abandono el Checkout. */
    #[Route('/pago/cancelado/{id}', name: 'inscripcion_pago_cancelado', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function cancelado(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser($request);
        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $sessionId = trim((string) $request->query->get('session_id', ''));
        if ($sessionId !== '') {
            try {
                $this->actualizarEstadoPago->execute($sessionId, EstadoPago::CANCELADO, (int) $user->id);
            } catch (InscripcionException) {
                // El retorno cancelado no debe romper la navegacion del postulante.
            }
        }

        $this->addFlash('error', 'Pago cancelado. Puedes intentarlo nuevamente cuando quieras.');

        return $this->redirectToRoute('inscripcion_ver', ['id' => $id]);
    }

    /**
     * Panel administrativo: pagos de una gestion.
     *
     * priority alta: la ruta estatica /admin/pagos debe resolverse ANTES que
     * /admin/{id} (inscripcion_admin_detalle), que si no capturaria "pagos".
     */
    #[Route('/admin/pagos', name: 'inscripcion_admin_pagos', methods: ['GET'], priority: 10)]
    public function adminPagos(Request $request): Response
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        $gestiones = $this->gestiones->listAll();
        $gestionIdParam = $request->query->getInt('gestion', 0);
        $gestion = $gestionIdParam > 0
            ? $this->gestiones->findById($gestionIdParam)
            : $this->gestiones->findActive();

        $estado = trim((string) $request->query->get('estado', ''));

        $pagos = $gestion !== null ? $this->pagos->listByGestion($gestion->id, $estado) : [];
        $resumen = $gestion !== null
            ? $this->pagos->resumenGestion($gestion->id)
            : ['recaudado' => 0, 'pagados' => 0, 'pendientes' => 0];

        return $this->render('@inscripcion/admin_pagos.html.twig', [
            'pagos' => $pagos,
            'resumen' => $resumen,
            'gestion' => $gestion,
            'gestiones' => $gestiones,
            'estados' => EstadoPago::all(),
            'estadoSeleccionado' => $estado,
            'pasarelaConfigurada' => $this->stripe->isConfigured(),
            'user' => $this->currentUser($request),
            'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
        ]);
    }

    /**
     * Registro de pago MANUAL por un administrador (respaldo sin pasarela):
     * marca el pago como pagado y confirma la inscripcion.
     */
    #[Route('/admin/pagos/{id}/registrar', name: 'inscripcion_admin_pagos_registrar', methods: ['POST'], requirements: ['id' => '\d+'], priority: 10)]
    public function registrarManual(Request $request, int $id): RedirectResponse
    {
        if ($this->currentUser($request) === null) {
            return $this->redirectToRoute('auth_login');
        }

        if (!$this->csrf->validate(self::CSRF_INTENTION, (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', self::CSRF_ERROR);

            return $this->redirectToPagos($request);
        }

        try {
            $this->registrarPagoManual->execute($id, $this->actorUserId($request));
            $this->addFlash('success', 'Pago registrado manualmente. La inscripcion fue confirmada.');
        } catch (InscripcionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToPagos($request);
    }

    private function redirectToPagos(Request $request): RedirectResponse
    {
        $gestionId = (int) $request->request->get('gestion', 0);

        return $gestionId > 0
            ? $this->redirectToRoute('inscripcion_admin_pagos', ['gestion' => $gestionId])
            : $this->redirectToRoute('inscripcion_admin_pagos');
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
