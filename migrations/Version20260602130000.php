<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove cupo base from carreras catalog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carreras DROP COLUMN IF EXISTS cupo_base');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carreras ADD cupo_base INT DEFAULT NULL');
    }
}
