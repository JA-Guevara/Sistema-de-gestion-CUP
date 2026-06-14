<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614160000 extends AbstractMigration
{
    private const NEW_PERMISSIONS = [
        ['usuarios.gestionar', 'Usuarios', 'Gestionar', 'Crear, editar, activar y resetear usuarios'],
        ['roles.gestionar', 'Roles y permisos', 'Gestionar', 'Crear, editar y activar roles'],
        ['gestion.gestionar', 'Gestion CUP', 'Gestionar', 'Crear, editar, activar y cerrar gestiones CUP'],
        ['academico.gestionar', 'Administracion academica', 'Gestionar', 'Crear, editar, activar y generar catalogos academicos'],
        ['inscripciones.gestionar', 'Inscripciones', 'Gestionar', 'Validar, rechazar, confirmar y administrar inscripciones'],
        ['pagos.ver', 'Pagos', 'Ver', 'Ver pagos de inscripcion'],
        ['pagos.gestionar', 'Pagos', 'Gestionar', 'Conciliar y administrar pagos de inscripcion'],
        ['asignaciones.ver', 'Asignaciones', 'Ver', 'Ver asignaciones academicas'],
        ['asignaciones.gestionar', 'Asignaciones', 'Gestionar', 'Asignar roles, docentes y estudiantes'],
        ['notas.gestionar', 'Notas', 'Gestionar', 'Registrar, importar, exportar y administrar notas'],
    ];

    private const NEW_CODES = [
        'usuarios.gestionar',
        'roles.gestionar',
        'gestion.gestionar',
        'academico.gestionar',
        'inscripciones.gestionar',
        'pagos.ver',
        'pagos.gestionar',
        'asignaciones.ver',
        'asignaciones.gestionar',
        'notas.gestionar',
    ];

    public function getDescription(): string
    {
        return 'Normalizar roles y permisos con esquema Ver/Gestionar por modulo';
    }

    public function up(Schema $schema): void
    {
        $this->createPermissions();
        $this->grantDefaults();
        $this->grantFromLegacyPermissions();
    }

    public function down(Schema $schema): void
    {
        $codes = $this->sqlList(self::NEW_CODES);

        $this->addSql("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE code IN ($codes))");
        $this->addSql("DELETE FROM permissions WHERE code IN ($codes)");
    }

    private function createPermissions(): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz')))->format('Y-m-d H:i:s');

        foreach (self::NEW_PERMISSIONS as [$code, $module, $action, $description]) {
            $this->addSql(sprintf(
                'INSERT INTO permissions (code, module, action, description, active, created_at)
                 SELECT %s, %s, %s, %s, true, %s
                 WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = %s)',
                $this->quote($code),
                $this->quote($module),
                $this->quote($action),
                $this->quote($description),
                $this->quote($now),
                $this->quote($code),
            ));
        }
    }

    private function grantDefaults(): void
    {
        $this->grantToRole('Administrador', self::NEW_CODES);

        $this->grantToRole('Coordinador', [
            'gestion.gestionar',
            'academico.gestionar',
            'inscripciones.gestionar',
            'pagos.ver',
            'pagos.gestionar',
            'asignaciones.ver',
            'asignaciones.gestionar',
            'notas.ver',
            'bitacora.ver',
        ]);

        $this->grantToRole('Docente', [
            'notas.ver',
            'notas.gestionar',
        ]);

        $this->grantToRole('Estudiante', [
            'inscripciones.ver',
            'notas.ver',
        ]);

        $this->grantToRole('Postulante', [
            'inscripciones.ver',
        ]);
    }

    private function grantFromLegacyPermissions(): void
    {
        $this->grantByLegacy('usuarios.gestionar', ['usuarios.crear', 'usuarios.editar', 'usuarios.estado', 'usuarios.reset']);
        $this->grantByLegacy('roles.gestionar', ['roles.crear', 'roles.editar', 'roles.estado']);
        $this->grantByLegacy('gestion.gestionar', ['gestion.crear', 'gestion.editar', 'gestion.activar']);
        $this->grantByLegacy('academico.gestionar', ['academico.crear', 'academico.editar', 'academico.estado']);
        $this->grantByLegacy('inscripciones.gestionar', ['inscripciones.validar']);
        $this->grantByLegacy('pagos.ver', ['inscripciones.validar']);
        $this->grantByLegacy('pagos.gestionar', ['inscripciones.validar']);
        $this->grantByLegacy('asignaciones.ver', ['notas.asignar']);
        $this->grantByLegacy('asignaciones.gestionar', ['notas.asignar']);
        $this->grantByLegacy('notas.gestionar', ['notas.registrar']);
    }

    /** @param list<string> $codes */
    private function grantToRole(string $roleName, array $codes): void
    {
        $this->addSql(sprintf(
            'INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM roles r
             JOIN permissions p ON p.code IN (%s)
             WHERE r.name = %s
             AND NOT EXISTS (
                 SELECT 1 FROM role_permissions rp
                 WHERE rp.role_id = r.id AND rp.permission_id = p.id
             )',
            $this->sqlList($codes),
            $this->quote($roleName),
        ));
    }

    /** @param list<string> $legacyCodes */
    private function grantByLegacy(string $newCode, array $legacyCodes): void
    {
        $this->addSql(sprintf(
            'INSERT INTO role_permissions (role_id, permission_id)
             SELECT DISTINCT rp.role_id, np.id
             FROM role_permissions rp
             JOIN permissions lp ON lp.id = rp.permission_id AND lp.code IN (%s)
             JOIN permissions np ON np.code = %s
             WHERE NOT EXISTS (
                 SELECT 1 FROM role_permissions existing
                 WHERE existing.role_id = rp.role_id AND existing.permission_id = np.id
             )',
            $this->sqlList($legacyCodes),
            $this->quote($newCode),
        ));
    }

    /** @param list<string> $values */
    private function sqlList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->quote($value), $values));
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
