<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Q6: ponderacion de examenes por gestion (promedio ponderado).
 * Q4: resultado de la Admision Final por estudiante (carrera adjudicada + estado).
 * Todas las columnas son nullable: no afectan filas existentes.
 */
final class Version20260617130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ponderaciones de examenes en la config de gestion y resultado de Admision Final en inscripciones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gestion_configuraciones ADD ponderaciones_examenes JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD carrera_admitida_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD resultado_admision VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD CONSTRAINT fk_inscripciones_carrera_admitida FOREIGN KEY (carrera_admitida_id) REFERENCES carreras (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_inscripciones_carrera_admitida ON inscripciones (carrera_admitida_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripciones DROP CONSTRAINT fk_inscripciones_carrera_admitida');
        $this->addSql('DROP INDEX idx_inscripciones_carrera_admitida');
        $this->addSql('ALTER TABLE inscripciones DROP resultado_admision');
        $this->addSql('ALTER TABLE inscripciones DROP carrera_admitida_id');
        $this->addSql('ALTER TABLE gestion_configuraciones DROP ponderaciones_examenes');
    }
}
