<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\ResendUnlockCode;
use App\Auth\Application\UseCase\UnlockAccount;
use App\Auth\Domain\Exception\UnlockCodeExpired;
use App\Auth\Domain\Exception\UnlockCodeInvalid;
use App\Auth\Domain\Exception\UnlockCodeRequestedTooSoon;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Auth\UI\Request\UnlockAccountRequest;
use App\Bitacora\Application\EventLog\AuthEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/unlock')]
final class UnlockAccountController extends AbstractController
{
    private const CSRF_INTENTION = 'auth_unlock';
    private const SESSION_PENDING_EMAIL = 'auth_pending_unlock_email';
    private const SESSION_PENDING_USER_ID = 'auth_pending_unlock_user_id';

    public function __construct(
        private readonly UnlockAccount $unlockAccount,
        private readonly ResendUnlockCode $resendUnlockCode,
        private readonly UserRepository $users,
        private readonly CsrfManager $csrf,
        private readonly AuthEvents $authEvents,
    ) {
    }

    #[Route('', name: 'auth_unlock')]
    public function unlock(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get(self::SESSION_PENDING_EMAIL);

        // Sin contexto de bloqueo: no hay nada que desbloquear, volver al login.
        if (!is_string($email) || $email === '') {
            return $this->redirectToRoute('auth_login');
        }

        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/unlock.html.twig', [
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
                'masked_email' => $this->maskEmail($email),
            ]);
        }

        // Validar CSRF
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesión expiró. Volvé a intentarlo.');

            return $this->redirectToRoute('auth_unlock');
        }

        // Aplicar el desbloqueo
        try {
            $this->unlockAccount->execute(new UnlockAccountRequest(
                email: $email,
                code: trim((string) $request->request->get('code', '')),
            ));
        } catch (UnlockCodeExpired $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_unlock');
        } catch (UnlockCodeInvalid $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_unlock');
        }

        // Log de desbloqueo exitoso ANTES de limpiar la sesión.
        $userId = $session->get(self::SESSION_PENDING_USER_ID);
        if (is_int($userId)) {
            $unlockedUser = $this->users->findById($userId);
            if ($unlockedUser !== null) {
                $this->authEvents->cuentaDesbloqueada($unlockedUser);
            }
        }

        // Limpiar contexto: el unlock se consumió.
        $session->remove(self::SESSION_PENDING_EMAIL);
        $session->remove(self::SESSION_PENDING_USER_ID);

        $this->addFlash('success', 'Cuenta desbloqueada. Ya podés iniciar sesión.');

        return $this->redirectToRoute('auth_login');
    }

    #[Route('/resend', name: 'auth_unlock_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        // Validar CSRF (mismo intent que el form principal)
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesión expiró. Volvé a intentarlo.');

            return $this->redirectToRoute('auth_unlock');
        }

        $email = $request->getSession()->get(self::SESSION_PENDING_EMAIL);
        if (!is_string($email) || $email === '') {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $this->resendUnlockCode->execute($email);
        } catch (UnlockCodeRequestedTooSoon $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_unlock');
        }

        $this->addFlash('success', 'Te enviamos un nuevo código. Revisá tu correo.');

        return $this->redirectToRoute('auth_unlock');
    }

    /**
     * Enmascara un email para mostrarlo en pantalla sin revelar el dominio o el local part completo.
     * Ej: "jose.guevara@gmail.com" → "j*****a@gmail.com"
     */
    private function maskEmail(string $email): string
    {
        $atPos = strpos($email, '@');
        if ($atPos === false || $atPos === 0) {
            return '***';
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos);

        $len = mb_strlen($local);

        if ($len <= 2) {
            return mb_substr($local, 0, 1).'***'.$domain;
        }

        return mb_substr($local, 0, 1)
            .str_repeat('*', max(3, $len - 2))
            .mb_substr($local, -1)
            .$domain;
    }
}
