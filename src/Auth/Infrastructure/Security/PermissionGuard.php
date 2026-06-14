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
 * Guard de autorización por permiso. Corre después del SessionGuard y, según la
 * ruta solicitada, exige que el usuario en sesión tenga el permiso requerido
 * (vía User::hasPermission()).
 *
 * Las reglas se centralizan en ROUTE_PERMISSIONS (prefijo de nombre de ruta =>
 * permiso). El orden importa: las entradas más específicas van primero. Las
 * rutas que no aparecen (home, perfil_, auth_, internas) no requieren permiso.
 *
 * - Sin sesión en una ruta protegida → redirige a login.
 * - Sin permiso → redirige al inicio con un mensaje (en vez de un 403 crudo).
 *
 * Para proteger un módulo nuevo: añadir su prefijo de ruta aquí.
 */
final readonly class PermissionGuard implements EventSubscriberInterface
{
    private const SESSION_USER_KEY = 'auth_user_id';

    /** @var array<string,string> Prefijo de _route => código de permiso. Más específico primero. */
    private const ROUTE_PERMISSIONS = [
        // Inscripción: las pantallas de administración exigen "validar"; las del propio postulante, "ver".
        'inscripcion_admin' => 'inscripciones.validar',
        'inscripcion_buscar' => 'inscripciones.validar',
        'inscripcion_edit' => 'inscripciones.validar',
        'inscripcion_delete' => 'inscripciones.validar',
        'inscripcion_documento' => 'inscripciones.validar',
        'inscripcion_' => 'inscripciones.ver',

        // Notas: asignar (admin/coord), registrar (docente), ver (boletín del postulante).
        'nota_asignacion' => 'notas.asignar',
        'nota_grupo' => 'notas.asignar',
        'nota_planilla' => 'notas.registrar',
        'nota_guardar' => 'notas.registrar',
        'nota_docente' => 'notas.registrar',
        'nota_' => 'notas.ver',

        // Hub de asignaciones.
        'asignacion_roles' => 'usuarios.ver',
        'asignacion_' => 'notas.asignar',

        // Gestión y dashboard.
        'gestion_' => 'gestion.ver',
        'dashboard_' => 'gestion.ver',

        // Catálogos académicos.
        'carrera_' => 'academico.ver',
        'materia_' => 'academico.ver',
        'aula_' => 'academico.ver',
        'grupo_' => 'academico.ver',
        'horario_' => 'academico.ver',

        // Seguridad.
        'usuario_' => 'usuarios.ver',
        'rol_' => 'roles.ver',

        // Auditoría.
        'bitacora_' => 'bitacora.ver',
    ];

    public function __construct(
        private UserRepository $users,
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Prioridad 6: después del SessionGuard (7), antes del controller.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 6],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');
        if (!is_string($routeName)) {
            return;
        }

        $permiso = $this->permisoParaRuta($routeName);
        if ($permiso === null) {
            return;
        }

        $userId = $request->getSession()->get(self::SESSION_USER_KEY);
        $user = is_int($userId) ? $this->users->findById($userId) : null;

        if ($user === null) {
            $event->setResponse(new RedirectResponse($this->router->generate('auth_login')));

            return;
        }

        if (!$user->hasPermission($permiso)) {
            $request->getSession()->getFlashBag()->add('error', 'No tienes permiso para acceder a esa seccion.');
            $event->setResponse(new RedirectResponse($this->router->generate('home')));
        }
    }

    private function permisoParaRuta(string $routeName): ?string
    {
        foreach (self::ROUTE_PERMISSIONS as $prefijo => $permiso) {
            if (str_starts_with($routeName, $prefijo)) {
                return $permiso;
            }
        }

        return null;
    }
}
