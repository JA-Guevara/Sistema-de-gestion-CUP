<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Area de conocimiento: la materia gana 'area' y la inscripcion del docente
 * gana 'docente_areas' (lista JSON de areas que puede dictar). Sirve para validar
 * que un docente solo reciba materias de su area. Ambas columnas nullable: las
 * filas existentes no se rompen.
 */
final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Area de conocimiento en materias y areas habilitadas del docente en inscripciones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE materias ADD area VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD docente_areas JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE materias DROP area');
        $this->addSql('ALTER TABLE inscripciones DROP docente_areas');
    }
}
