<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Revision de documentos: cada documento de inscripcion puede marcarse como
 * Aprobado u Observado, con una observacion visible para el postulante.
 */
final class Version20260613180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Estado y observacion por documento de inscripcion (revision)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inscripcion_documentos ADD estado VARCHAR(20) DEFAULT 'PENDIENTE' NOT NULL");
        $this->addSql('ALTER TABLE inscripcion_documentos ADD observacion TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripcion_documentos DROP estado');
        $this->addSql('ALTER TABLE inscripcion_documentos DROP observacion');
    }
}
