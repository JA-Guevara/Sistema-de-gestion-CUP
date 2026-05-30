<?php

declare(strict_types=1);

namespace App\Auth\UI\Controller;

use App\Auth\Application\UseCase\LoginUser;
use App\Auth\Domain\Exception\AccountLocked;
use App\Auth\Domain\Exception\InvalidCredentials;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Auth\Infrastructure\Security\CsrfManager;
use App\Auth\UI\Request\LoginRequest;
use App\Bitacora\Application\EventLog\AuthEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
final class LoginController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const CSRF_INTENTION = 'auth_login';

    public function __construct(
        private readonly LoginUser $loginUser,
        private readonly UserRepository $users,
        private readonly CsrfManager $csrf,
        private readonly AuthEvents $authEvents,
    ) {
    }

    #[Route('/login', name: 'auth_login')]
    public function login(Request $request): Response
    {
        // Si ya hay sesión activa, mandar a la home del logueado.
        if ($request->getSession()->has(self::SESSION_USER_KEY)) {
            return $this->redirectToRoute('home');
        }

        // Mostrar formulario
        if (!$request->isMethod('POST')) {
            return $this->render('@auth/login.html.twig', [
                'csrf_token' => $this->csrf->issue(self::CSRF_INTENTION),
            ]);
        }

        // Validar CSRF
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrf->validate(self::CSRF_INTENTION, $submittedToken)) {
            $this->addFlash('error', 'La sesión expiró. Volvé a intentarlo.');

            return $this->redirectToRoute('auth_login');
        }

        // Construir request DTO
        $input = new LoginRequest(
            email: trim(
                (string) $request->request->get('email', '')
            ),
            password: (string) $request->request->get('password', '')
        );

        // Autenticar
        try {
            $user = $this->loginUser->execute($input);
        } catch (AccountLocked $exception) {
            // Guardar contexto del usuario bloqueado en sesión para que
            // /auth/unlock sepa de quién es el código sin pedirle el email otra vez.
            $lockedUser = $this->users->findByEmail($input->email);
            if ($lockedUser !== null) {
                $session = $request->getSession();
                $session->set('auth_pending_unlock_user_id', $lockedUser->id);
                $session->set('auth_pending_unlock_email', $lockedUser->email);
                $this->authEvents->cuentaBloqueada($lockedUser);
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_unlock');
        } catch (InvalidCredentials $exception) {
            $this->authEvents->loginFallido($input->email);
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('auth_login');
        }

        // Regenerar el ID de sesión (previene session fixation)
        $session = $request->getSession();
        $session->migrate(true);

        // Guardar sesión y persistir datos de control
        $session->set(self::SESSION_USER_KEY, $user->id);
        $session->set('auth_last_activity', time());
        $user->currentSessionId = $session->getId();
        $user->lastLoginAt = new \DateTimeImmutable();
        $this->users->save($user);

        $this->authEvents->loginExitoso($user);

        return $this->redirectToRoute('home');
    }
}
