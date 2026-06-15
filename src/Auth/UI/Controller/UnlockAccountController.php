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

        if (!is_string($email) || $email === '') {
            return $this->redirectToRoute('auth_login');
        }

        if (!$request->isMethod('POST')) {
            return $this->render('@auth/unlock.html.twig', [
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
                'masked_email' => $this->maskEmail($email),
            ]);
        }

        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesion expiro. Volve a intentarlo.');

            return $this->redirectToRoute('auth_unlock');
        }

        try {
            $this->unlockAccount->execute(new UnlockAccountRequest(
                email: $email,
                code: trim((string) $request->request->get('code', '')),
            ));
        } catch (UnlockCodeExpired|UnlockCodeInvalid $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_unlock');
        }

        $this->registerSuccessfulUnlock($session->get(self::SESSION_PENDING_USER_ID));
        $session->remove(self::SESSION_PENDING_EMAIL);
        $session->remove(self::SESSION_PENDING_USER_ID);

        $this->addFlash('success', 'Cuenta desbloqueada. Ya podes iniciar sesion.');

        return $this->redirectToRoute('auth_login');
    }

    #[Route('/resend', name: 'auth_unlock_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesion expiro. Volve a intentarlo.');

            return $this->redirectToRoute('auth_unlock');
        }

        $email = $request->getSession()->get(self::SESSION_PENDING_EMAIL);
        if (!is_string($email) || $email === '') {
            return $this->redirectToRoute('auth_login');
        }

        try {
            $sent = $this->resendUnlockCode->execute($email);
        } catch (UnlockCodeRequestedTooSoon $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_unlock');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'No pudimos enviar el codigo en este momento. Revisa la configuracion SMTP o intenta otra vez.');

            return $this->redirectToRoute('auth_unlock');
        }

        if (!$sent) {
            $this->addFlash('error', 'La cuenta ya no esta bloqueada o no existe un codigo pendiente.');

            return $this->redirectToRoute('auth_unlock');
        }

        $this->addFlash('success', 'Te enviamos un nuevo codigo. Revisa tu correo.');

        return $this->redirectToRoute('auth_unlock');
    }

    private function registerSuccessfulUnlock(mixed $userId): void
    {
        if (!is_int($userId)) {
            return;
        }

        $unlockedUser = $this->users->findById($userId);
        if ($unlockedUser !== null) {
            $this->authEvents->cuentaDesbloqueada($unlockedUser);
        }
    }

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
            return mb_substr($local, 0, 1) . '***' . $domain;
        }

        return mb_substr($local, 0, 1)
            . str_repeat('*', max(3, $len - 2))
            . mb_substr($local, -1)
            . $domain;
    }
}
