<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Asigna el rol Administrador a los usuarios del equipo y concede notas.ver
 * al rol Postulante (para que cada estudiante pueda ver su boletín).
 *
 * Los emails son específicos del entorno actual; en una BD distinta las
 * inserciones de admin simplemente no afectarán filas (WHERE no encuentra match).
 */
final class Version20260613140000 extends AbstractMigration
{
    private const ADMIN_EMAILS = [
        'jose.guevara1caballero@gmail.com',
        'diegoastetepaz@gmail.com',
    ];

    public function getDescription(): string
    {
        return 'Asignar rol Administrador a usuarios del equipo y dar notas.ver al Postulante';
    }

    public function up(Schema $schema): void
    {
        $emails = "'" . implode("','", self::ADMIN_EMAILS) . "'";

        $this->addSql("INSERT INTO user_roles (user_id, role_id)
            SELECT u.id, r.id
            FROM users u
            CROSS JOIN roles r
            WHERE u.email IN ($emails) AND r.name = 'Administrador'
            ON CONFLICT DO NOTHING");

        $this->addSql("INSERT INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r
            JOIN permissions p ON p.code = 'notas.ver'
            WHERE r.name = 'Postulante'
            ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $emails = "'" . implode("','", self::ADMIN_EMAILS) . "'";

        $this->addSql("DELETE FROM user_roles
            WHERE role_id = (SELECT id FROM roles WHERE name = 'Administrador')
            AND user_id IN (SELECT id FROM users WHERE email IN ($emails))");

        $this->addSql("DELETE FROM role_permissions
            WHERE role_id = (SELECT id FROM roles WHERE name = 'Postulante')
            AND permission_id = (SELECT id FROM permissions WHERE code = 'notas.ver')");
    }
}
