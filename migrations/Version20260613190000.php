<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multiples postulaciones: un postulante puede crear varias inscripciones
 * (reintentos y/o como estudiante y como docente). La unicidad pasa a ser
 * por CI + tipo solo cuando se CONFIRMA (se valida en la aplicacion). Por eso
 * se quitan los UNIQUE de (user, gestion) y de ci, dejando indices normales.
 */
final class Version20260613190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permitir multiples postulaciones: quitar UNIQUE(user,gestion) y UNIQUE(ci)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_inscripciones_user_gestion');
        $this->addSql('DROP INDEX uniq_inscripciones_ci');
        $this->addSql('CREATE INDEX idx_inscripciones_user_gestion ON inscripciones (user_id, gestion_id)');
        $this->addSql('CREATE INDEX idx_inscripciones_ci ON inscripciones (ci)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_inscripciones_user_gestion');
        $this->addSql('DROP INDEX idx_inscripciones_ci');
        $this->addSql('CREATE UNIQUE INDEX uniq_inscripciones_user_gestion ON inscripciones (user_id, gestion_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_inscripciones_ci ON inscripciones (ci)');
    }
}
