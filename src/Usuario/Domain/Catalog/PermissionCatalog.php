<?php

declare(strict_types=1);

namespace App\Usuario\Domain\Catalog;

final class PermissionCatalog
{
    /** @return list<array{code:string,module:string,action:string,description:string}> */
    public static function all(): array
    {
        return [
            ['code' => 'usuarios.ver', 'module' => 'Usuarios', 'action' => 'Ver', 'description' => 'Ver usuarios'],
            ['code' => 'usuarios.gestionar', 'module' => 'Usuarios', 'action' => 'Gestionar', 'description' => 'Crear, editar, activar y resetear usuarios'],
            ['code' => 'roles.ver', 'module' => 'Roles y permisos', 'action' => 'Ver', 'description' => 'Ver roles'],
            ['code' => 'roles.gestionar', 'module' => 'Roles y permisos', 'action' => 'Gestionar', 'description' => 'Crear, editar y activar roles'],
            ['code' => 'gestion.ver', 'module' => 'Gestion CUP', 'action' => 'Ver', 'description' => 'Ver gestiones CUP'],
            ['code' => 'gestion.gestionar', 'module' => 'Gestion CUP', 'action' => 'Gestionar', 'description' => 'Crear, editar, activar y cerrar gestiones CUP'],
            ['code' => 'academico.ver', 'module' => 'Administracion academica', 'action' => 'Ver', 'description' => 'Ver catalogos academicos'],
            ['code' => 'academico.gestionar', 'module' => 'Administracion academica', 'action' => 'Gestionar', 'description' => 'Crear, editar, activar y generar catalogos academicos'],
            ['code' => 'inscripciones.ver', 'module' => 'Inscripciones', 'action' => 'Ver', 'description' => 'Ver inscripciones CUP'],
            ['code' => 'inscripciones.gestionar', 'module' => 'Inscripciones', 'action' => 'Gestionar', 'description' => 'Validar, rechazar, confirmar y administrar inscripciones'],
            ['code' => 'pagos.ver', 'module' => 'Pagos', 'action' => 'Ver', 'description' => 'Ver pagos de inscripcion'],
            ['code' => 'pagos.gestionar', 'module' => 'Pagos', 'action' => 'Gestionar', 'description' => 'Conciliar y administrar pagos de inscripcion'],
            ['code' => 'asignaciones.ver', 'module' => 'Asignaciones', 'action' => 'Ver', 'description' => 'Ver asignaciones academicas'],
            ['code' => 'asignaciones.gestionar', 'module' => 'Asignaciones', 'action' => 'Gestionar', 'description' => 'Asignar roles, docentes y estudiantes'],
            ['code' => 'notas.ver', 'module' => 'Notas', 'action' => 'Ver', 'description' => 'Ver notas y boletines'],
            ['code' => 'notas.gestionar', 'module' => 'Notas', 'action' => 'Gestionar', 'description' => 'Registrar, importar, exportar y administrar notas'],
            ['code' => 'bitacora.ver', 'module' => 'Bitacora', 'action' => 'Ver', 'description' => 'Consultar bitacora'],
        ];
    }

    /** @return list<string> */
    public static function assignableCodes(): array
    {
        return array_map(
            static fn (array $permission): string => $permission['code'],
            self::all(),
        );
    }

    /** @return list<string> */
    public static function legacyCodes(): array
    {
        return [
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.estado',
            'usuarios.reset',
            'roles.crear',
            'roles.editar',
            'roles.estado',
            'gestion.crear',
            'gestion.editar',
            'gestion.activar',
            'academico.crear',
            'academico.editar',
            'academico.estado',
            'inscripciones.validar',
            'notas.registrar',
            'notas.asignar',
        ];
    }

    public static function isAssignable(string $code): bool
    {
        return in_array($code, self::assignableCodes(), true);
    }
}
