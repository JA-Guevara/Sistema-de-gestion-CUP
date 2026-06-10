<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * No-op. (Se descartó la ampliación de columnas role/entity/result en log_entries;
 * los campos enterprise viajan en metadata JSON, sin cambios de esquema.)
 */
final class Version20260603160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op (descartada la ampliacion de columnas de log_entries)';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
