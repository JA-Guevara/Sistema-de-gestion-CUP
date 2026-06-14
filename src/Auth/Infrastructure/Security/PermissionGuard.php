<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Guard de autorizacion por permiso. Corre despues del SessionGuard y exige que
 * el usuario tenga al menos uno de los permisos configurados para la ruta.
 */
final readonly class PermissionGuard implements EventSubscriberInterface
{
    private const SESSION_USER_KEY = 'auth_user_id';

    /**
     * Prefijo de _route => permisos aceptados. Mas especifico primero.
     *
     * El esquema nuevo usa *.ver y *.gestionar. Los permisos antiguos quedan
     * como alternativa temporal para no romper roles existentes.
     *
     * @var array<string,string|list<string>>
     */
    private const ROUTE_PERMISSIONS = [
        // Inscripcion y pagos.
        'inscripcion_admin_pagos' => ['pagos.ver', 'pagos.gestionar', 'inscripciones.validar'],
        'inscripcion_admin' => ['inscripciones.gestionar', 'inscripciones.validar'],
        'inscripcion_buscar' => ['inscripciones.gestionar', 'inscripciones.validar'],
        'inscripcion_edit' => ['inscripciones.gestionar', 'inscripciones.validar'],
        'inscripcion_delete' => ['inscripciones.gestionar', 'inscripciones.validar'],
        'inscripcion_documento' => ['inscripciones.gestionar', 'inscripciones.validar'],
        'inscripcion_pago' => 'inscripciones.ver',
        'inscripcion_' => ['inscripciones.ver', 'inscripciones.gestionar', 'inscripciones.validar'],

        // Asignaciones y notas.
        'nota_asignacion' => ['asignaciones.gestionar', 'notas.asignar'],
        'nota_grupo' => ['asignaciones.gestionar', 'notas.asignar'],
        'nota_planilla_importar' => ['notas.gestionar', 'notas.registrar'],
        'nota_planilla_exportar' => ['notas.gestionar', 'notas.registrar'],
        'nota_planilla' => ['notas.gestionar', 'notas.registrar'],
        'nota_guardar' => ['notas.gestionar', 'notas.registrar'],
        'nota_docente' => ['notas.gestionar', 'notas.registrar'],
        'nota_' => ['notas.ver', 'notas.gestionar', 'notas.registrar'],

        'asignacion_roles' => ['asignaciones.gestionar', 'usuarios.gestionar', 'notas.asignar'],
        'asignacion_' => ['asignaciones.ver', 'asignaciones.gestionar', 'notas.asignar'],

        // Gestion y dashboard.
        'gestion_new' => ['gestion.gestionar', 'gestion.crear'],
        'gestion_edit' => ['gestion.gestionar', 'gestion.editar'],
        'gestion_activate' => ['gestion.gestionar', 'gestion.activar'],
        'gestion_open_inscription' => ['gestion.gestionar', 'gestion.activar'],
        'gestion_close_inscription' => ['gestion.gestionar', 'gestion.activar'],
        'gestion_' => ['gestion.ver', 'gestion.gestionar'],
        'dashboard_' => ['gestion.ver', 'gestion.gestionar'],

        // Catalogos academicos.
        'carrera_new' => ['academico.gestionar', 'academico.crear'],
        'carrera_edit' => ['academico.gestionar', 'academico.editar'],
        'carrera_activate' => ['academico.gestionar', 'academico.estado'],
        'carrera_deactivate' => ['academico.gestionar', 'academico.estado'],
        'carrera_' => ['academico.ver', 'academico.gestionar'],
        'materia_new' => ['academico.gestionar', 'academico.crear'],
        'materia_edit' => ['academico.gestionar', 'academico.editar'],
        'materia_toggle' => ['academico.gestionar', 'academico.estado'],
        'materia_' => ['academico.ver', 'academico.gestionar'],
        'aula_new' => ['academico.gestionar', 'academico.crear'],
        'aula_generate' => ['academico.gestionar', 'academico.crear'],
        'aula_bulk_state' => ['academico.gestionar', 'academico.estado'],
        'aula_edit' => ['academico.gestionar', 'academico.editar'],
        'aula_toggle' => ['academico.gestionar', 'academico.estado'],
        'aula_' => ['academico.ver', 'academico.gestionar'],
        'grupo_new' => ['academico.gestionar', 'academico.crear'],
        'grupo_generate' => ['academico.gestionar', 'academico.crear'],
        'grupo_bulk_state' => ['academico.gestionar', 'academico.estado'],
        'grupo_edit' => ['academico.gestionar', 'academico.editar'],
        'grupo_toggle' => ['academico.gestionar', 'academico.estado'],
        'grupo_' => ['academico.ver', 'academico.gestionar'],
        'horario_multigrupo' => ['academico.gestionar', 'academico.crear'],
        'horario_grupo_materia_delete' => ['academico.gestionar', 'academico.estado'],
        'horario_grupo_delete' => ['academico.gestionar', 'academico.estado'],
        'horario_grupos_delete' => ['academico.gestionar', 'academico.estado'],
        'horario_masivo' => ['academico.gestionar', 'academico.crear'],
        'horario_edit' => ['academico.gestionar', 'academico.editar'],
        'horario_delete' => ['academico.gestionar', 'academico.estado'],
        'horario_' => ['academico.ver', 'academico.gestionar'],

        // Seguridad.
        'usuario_new' => ['usuarios.gestionar', 'usuarios.crear'],
        'usuario_edit' => ['usuarios.gestionar', 'usuarios.editar'],
        'usuario_toggle' => ['usuarios.gestionar', 'usuarios.estado'],
        'usuario_reset_password' => ['usuarios.gestionar', 'usuarios.reset'],
        'usuario_' => ['usuarios.ver', 'usuarios.gestionar'],
        'rol_new' => ['roles.gestionar', 'roles.crear'],
        'rol_edit' => ['roles.gestionar', 'roles.editar'],
        'rol_toggle' => ['roles.gestionar', 'roles.estado'],
        'rol_' => ['roles.ver', 'roles.gestionar'],

        // Auditoria.
        'bitacora_' => 'bitacora.ver',
    ];

    public function __construct(
        private UserRepository $users,
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
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

        $permisos = $this->permisosParaRuta($routeName);
        if ($permisos === null) {
            return;
        }

        $userId = $request->getSession()->get(self::SESSION_USER_KEY);
        $user = is_int($userId) ? $this->users->findById($userId) : null;

        if ($user === null) {
            $event->setResponse(new RedirectResponse($this->router->generate('auth_login')));

            return;
        }

        if (!$this->userHasAnyPermission($user, $permisos)) {
            $request->getSession()->getFlashBag()->add('error', 'No tienes permiso para acceder a esa seccion.');
            $event->setResponse(new RedirectResponse($this->router->generate('home')));
        }
    }

    /** @return list<string>|null */
    private function permisosParaRuta(string $routeName): ?array
    {
        foreach (self::ROUTE_PERMISSIONS as $prefijo => $permisos) {
            if (str_starts_with($routeName, $prefijo)) {
                return is_array($permisos) ? $permisos : [$permisos];
            }
        }

        return null;
    }

    /** @param list<string> $permisos */
    private function userHasAnyPermission(User $user, array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($user->hasPermission($permiso)) {
                return true;
            }
        }

        return false;
    }
}
