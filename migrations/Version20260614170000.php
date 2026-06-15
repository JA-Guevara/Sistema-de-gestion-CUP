<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige el modelo de roles para los nuevos modulos: el rol Postulante ya NO
 * ve Notas/boletin/horario. La consulta de notas queda solo para el rol
 * Estudiante (que se asigna al CONFIRMAR la inscripcion).
 *
 * La migracion 20260613140000 habia dado notas.ver a Postulante cuando aun no
 * existia el rol Estudiante; ahora que existe, se revierte ese permiso.
 */
final class Version20260614170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Quitar notas.ver del rol Postulante (solo el rol Estudiante ve notas/boletin/horario)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permissions
            WHERE role_id = (SELECT id FROM roles WHERE name = 'Postulante')
            AND permission_id = (SELECT id FROM permissions WHERE code = 'notas.ver')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r
            JOIN permissions p ON p.code = 'notas.ver'
            WHERE r.name = 'Postulante'
            ON CONFLICT DO NOTHING");
    }
}
