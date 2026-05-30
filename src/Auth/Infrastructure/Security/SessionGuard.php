<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Guard global de sesión: corre antes de cada controller y aplica:
 *
 * 1. Single-session: si la sesión actual no coincide con la registrada en el
 *    usuario (porque se logueó en otro dispositivo), invalida y manda a login.
 * 2. Timeout por inactividad: si pasaron más de TIMEOUT_SECONDS sin requests,
 *    invalida y manda a login.
 * 3. Renueva el timestamp de actividad si todo está OK.
 *
 * Rutas públicas (login, register, forgot, reset) están exentas del guard.
 */
final readonly class SessionGuard implements EventSubscriberInterface
{
    private const SESSION_USER_KEY = 'auth_user_id';
    private const SESSION_LAST_ACTIVITY = 'auth_last_activity';
    private const TIMEOUT_SECONDS = 1800; // 30 minutos

    /** Rutas que NO requieren sesión activa. */
    private const PUBLIC_ROUTE_PREFIXES = [
        'auth_login',
        'auth_register',
        'auth_forgot',
        'auth_reset',
        'auth_unlock',
    ];

    public function __construct(
        private UserRepository $users,
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Prioridad 7: corre después del router (que asigna _route) y antes del controller.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');

        // Rutas públicas: el guard no aplica.
        if ($this->isPublicRoute($routeName)) {
            return;
        }

        $session = $request->getSession();
        $userId = $session->get(self::SESSION_USER_KEY);

        // Sin sesión iniciada: dejar que el controller decida (home y auth_logout ya lo manejan).
        if (!is_int($userId)) {
            return;
        }

        $user = $this->users->findById($userId);

        // Usuario eliminado o session ID divergente → cerrar todo y mandar a login.
        if ($user === null || $user->currentSessionId !== $session->getId()) {
            $this->forceLogout($event, 'Tu sesión se cerró porque iniciaste sesión desde otro lugar.');

            return;
        }

        // Timeout por inactividad.
        $lastActivity = $session->get(self::SESSION_LAST_ACTIVITY);
        if (is_int($lastActivity) && (time() - $lastActivity) > self::TIMEOUT_SECONDS) {
            $this->forceLogout($event, 'Tu sesión expiró por inactividad. Volvé a iniciar sesión.');

            return;
        }

        // Todo OK: renovar el timestamp de actividad.
        $session->set(self::SESSION_LAST_ACTIVITY, time());
    }

    private function isPublicRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            // Ruta interna de Symfony (profiler, assets, etc.): dejar pasar.
            return true;
        }

        foreach (self::PUBLIC_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function forceLogout(RequestEvent $event, string $message): void
    {
        $session = $event->getRequest()->getSession();
        $session->invalidate();
        $session->getFlashBag()->add('error', $message);

        $event->setResponse(
            new RedirectResponse($this->router->generate('auth_login'))
        );
    }
}
