<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Admisiones (confirmacion): campos para la validacion de documentos,
 * entrevista (docente), motivo de rechazo y trazabilidad de quien valido/confirmo.
 */
final class Version20260613170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Campos de admision/confirmacion en inscripciones (validacion, entrevista, rechazo)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripciones ADD fecha_entrevista TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD validada_por INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD confirmada_por INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscripciones ADD motivo_rechazo TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscripciones DROP fecha_entrevista');
        $this->addSql('ALTER TABLE inscripciones DROP validada_por');
        $this->addSql('ALTER TABLE inscripciones DROP confirmada_por');
        $this->addSql('ALTER TABLE inscripciones DROP motivo_rechazo');
    }
}
