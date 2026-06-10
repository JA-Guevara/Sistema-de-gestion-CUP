<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow custom academic schedule activity names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gestion_periodos ALTER tipo_periodo TYPE VARCHAR(120)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gestion_periodos ALTER tipo_periodo TYPE VARCHAR(40)');
    }
}
