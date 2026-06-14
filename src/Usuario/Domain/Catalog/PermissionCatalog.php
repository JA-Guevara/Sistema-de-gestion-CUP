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
            ['code' => 'usuarios.crear', 'module' => 'Usuarios', 'action' => 'Crear', 'description' => 'Crear usuarios'],
            ['code' => 'usuarios.editar', 'module' => 'Usuarios', 'action' => 'Editar', 'description' => 'Editar usuarios'],
            ['code' => 'usuarios.estado', 'module' => 'Usuarios', 'action' => 'Estado', 'description' => 'Activar o desactivar usuarios'],
            ['code' => 'usuarios.reset', 'module' => 'Usuarios', 'action' => 'Reset', 'description' => 'Enviar recuperacion de contrasena'],
            ['code' => 'roles.ver', 'module' => 'Roles y permisos', 'action' => 'Ver', 'description' => 'Ver roles'],
            ['code' => 'roles.crear', 'module' => 'Roles y permisos', 'action' => 'Crear', 'description' => 'Crear roles'],
            ['code' => 'roles.editar', 'module' => 'Roles y permisos', 'action' => 'Editar', 'description' => 'Editar roles y permisos'],
            ['code' => 'roles.estado', 'module' => 'Roles y permisos', 'action' => 'Estado', 'description' => 'Activar o desactivar roles'],
            ['code' => 'gestion.ver', 'module' => 'Gestion CUP', 'action' => 'Ver', 'description' => 'Ver gestiones CUP'],
            ['code' => 'gestion.crear', 'module' => 'Gestion CUP', 'action' => 'Crear', 'description' => 'Crear gestiones CUP'],
            ['code' => 'gestion.editar', 'module' => 'Gestion CUP', 'action' => 'Editar', 'description' => 'Editar parametros y cronograma'],
            ['code' => 'gestion.activar', 'module' => 'Gestion CUP', 'action' => 'Activar', 'description' => 'Activar gestion CUP'],
            ['code' => 'academico.ver', 'module' => 'Administracion academica', 'action' => 'Ver', 'description' => 'Ver catalogos academicos'],
            ['code' => 'academico.crear', 'module' => 'Administracion academica', 'action' => 'Crear', 'description' => 'Crear catalogos academicos'],
            ['code' => 'academico.editar', 'module' => 'Administracion academica', 'action' => 'Editar', 'description' => 'Editar catalogos academicos'],
            ['code' => 'academico.estado', 'module' => 'Administracion academica', 'action' => 'Estado', 'description' => 'Activar o desactivar catalogos academicos'],
            ['code' => 'bitacora.ver', 'module' => 'Bitacora', 'action' => 'Ver', 'description' => 'Consultar bitacora'],
            ['code' => 'inscripciones.ver', 'module' => 'Inscripciones', 'action' => 'Ver', 'description' => 'Ver inscripciones CUP'],
            ['code' => 'inscripciones.validar', 'module' => 'Inscripciones', 'action' => 'Validar', 'description' => 'Validar documentos e inscripciones'],
            ['code' => 'notas.ver', 'module' => 'Notas', 'action' => 'Ver', 'description' => 'Ver notas y boletines'],
            ['code' => 'notas.registrar', 'module' => 'Notas', 'action' => 'Registrar', 'description' => 'Registrar y editar notas'],
            ['code' => 'notas.asignar', 'module' => 'Notas', 'action' => 'Asignar', 'description' => 'Asignar docentes a materias'],
        ];
    }
}
