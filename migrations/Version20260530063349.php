<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530063349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users ADD failed_login_attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE users ADD locked BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE users ADD unlock_code VARCHAR(6) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD unlock_code_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD unlock_code_last_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP failed_login_attempts');
        $this->addSql('ALTER TABLE users DROP locked');
        $this->addSql('ALTER TABLE users DROP unlock_code');
        $this->addSql('ALTER TABLE users DROP unlock_code_expires_at');
        $this->addSql('ALTER TABLE users DROP unlock_code_last_sent_at');
    }
}
