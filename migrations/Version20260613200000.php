<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crea el rol "Estudiante" (postulante admitido tras pagar/confirmar) con los
 * permisos para ver sus notas/boletin y sus inscripciones. La confirmacion de
 * una postulacion de estudiante asigna este rol (confirmar = asignar rol).
 */
final class Version20260613200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed rol Estudiante (admitido) con permisos notas.ver e inscripciones.ver';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz')))->format('Y-m-d H:i:s');

        // Crear el rol Estudiante solo si aun no existe.
        $this->addSql("INSERT INTO roles (name, description, active, created_at)
            SELECT 'Estudiante', 'Estudiante admitido del CUP: ve sus notas y boletin.', true, '$now'
            WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Estudiante')");

        // Permisos del Estudiante: ver notas/boletin y sus inscripciones.
        $this->addSql("INSERT INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r
            JOIN permissions p ON p.code IN ('notas.ver', 'inscripciones.ver')
            WHERE r.name = 'Estudiante'
            ON CONFLICT DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permissions WHERE role_id IN (SELECT id FROM roles WHERE name = 'Estudiante')");
        $this->addSql("DELETE FROM user_roles WHERE role_id IN (SELECT id FROM roles WHERE name = 'Estudiante')");
        $this->addSql("DELETE FROM roles WHERE name = 'Estudiante'");
    }
}
