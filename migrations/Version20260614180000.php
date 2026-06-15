<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Turno por grupo: permite filtrar dinamicamente los grupos por turno
 * (Manana/Tarde/Noche) en las asignaciones. Nullable: los grupos generados en
 * masa quedan sin turno hasta que el admin lo asigne.
 */
final class Version20260614180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agregar turno_id (nullable) a grupos para filtrar por turno';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE grupos ADD turno_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_grupos_turno ON grupos (turno_id)');
        $this->addSql('ALTER TABLE grupos ADD CONSTRAINT fk_grupos_turno FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE grupos DROP CONSTRAINT fk_grupos_turno');
        $this->addSql('DROP INDEX idx_grupos_turno');
        $this->addSql('ALTER TABLE grupos DROP turno_id');
    }
}
