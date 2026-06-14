<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pre-inscripcion unificada: la inscripcion ahora puede ser de tipo ESTUDIANTE
 * o DOCENTE, con modalidad presencial/virtual, segunda opcion de carrera y
 * datos profesionales del docente. La carrera pasa a ser opcional (el docente
 * no postula a una carrera).
 */
final class Version20260613160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pre-inscripcion unificada estudiante/docente: tipo, modalidad, 2a carrera y datos docente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inscripciones ADD tipo VARCHAR(20) DEFAULT 'ESTUDIANTE' NOT NULL");
        $this->addSql("ALTER TABLE inscripciones ADD modalidad VARCHAR(20) DEFAULT 'PRESENCIAL' NOT NULL");

        $this->addSql('ALTER TABLE inscripciones ALTER COLUMN carrera_id DROP NOT NULL');

        $this->addSql('ALTER TABLE inscripciones ADD carrera_segunda_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD CONSTRAINT fk_inscripciones_carrera_segunda FOREIGN KEY (carrera_segunda_id) REFERENCES carreras (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_inscripciones_carrera_segunda ON inscripciones (carrera_segunda_id)');

        $this->addSql('ALTER TABLE inscripciones ADD docente_profesion VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD docente_maestria BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD docente_diplomado BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD docente_experiencia TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE inscripciones ADD fecha_presentacion_docs TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_inscripciones_tipo ON inscripciones (tipo)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_inscripciones_tipo');
        $this->addSql('ALTER TABLE inscripciones DROP fecha_presentacion_docs');
        $this->addSql('ALTER TABLE inscripciones DROP docente_experiencia');
        $this->addSql('ALTER TABLE inscripciones DROP docente_diplomado');
        $this->addSql('ALTER TABLE inscripciones DROP docente_maestria');
        $this->addSql('ALTER TABLE inscripciones DROP docente_profesion');
        $this->addSql('DROP INDEX idx_inscripciones_carrera_segunda');
        $this->addSql('ALTER TABLE inscripciones DROP CONSTRAINT fk_inscripciones_carrera_segunda');
        $this->addSql('ALTER TABLE inscripciones DROP carrera_segunda_id');
        $this->addSql('ALTER TABLE inscripciones DROP modalidad');
        $this->addSql('ALTER TABLE inscripciones DROP tipo');
        // carrera_id se deja como nullable; restaurar NOT NULL podria fallar si hay docentes sin carrera.
    }
}
