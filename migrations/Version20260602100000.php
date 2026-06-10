<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove activa flag from gestiones and derive active state from estado and dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_gestiones_activa');
        $this->addSql('ALTER TABLE gestiones DROP COLUMN IF EXISTS activa');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gestiones ADD activa BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_gestiones_activa ON gestiones (activa)');
    }
}
