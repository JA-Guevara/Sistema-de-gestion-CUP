<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Soporte de anulacion de postulaciones: bandera de solicitud de anulacion y
 * su motivo. El estado ANULADA es un valor de catalogo (columna estado ya
 * existente), no requiere cambio de esquema adicional.
 */
final class Version20260614120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inscripciones: columnas anulacion_solicitada y motivo_anulacion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripciones ADD anulacion_solicitada BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD motivo_anulacion TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripciones DROP anulacion_solicitada');
        $this->addSql('ALTER TABLE inscripciones DROP motivo_anulacion');
    }
}
