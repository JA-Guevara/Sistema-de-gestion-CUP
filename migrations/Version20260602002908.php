<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602002908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE neon_auth.account DROP CONSTRAINT "account_userId_fkey"');
        $this->addSql('ALTER TABLE neon_auth.invitation DROP CONSTRAINT "invitation_organizationId_fkey"');
        $this->addSql('ALTER TABLE neon_auth.invitation DROP CONSTRAINT "invitation_inviterId_fkey"');
        $this->addSql('ALTER TABLE neon_auth.member DROP CONSTRAINT "member_organizationId_fkey"');
        $this->addSql('ALTER TABLE neon_auth.member DROP CONSTRAINT "member_userId_fkey"');
        $this->addSql('ALTER TABLE neon_auth.session DROP CONSTRAINT "session_userId_fkey"');
        $this->addSql('DROP TABLE neon_auth.account');
        $this->addSql('DROP TABLE neon_auth.invitation');
        $this->addSql('DROP TABLE neon_auth.jwks');
        $this->addSql('DROP TABLE neon_auth.member');
        $this->addSql('DROP TABLE neon_auth.organization');
        $this->addSql('DROP TABLE neon_auth.project_config');
        $this->addSql('DROP TABLE neon_auth.session');
        $this->addSql('DROP TABLE neon_auth."user"');
        $this->addSql('DROP TABLE neon_auth.verification');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA neon_auth');
        $this->addSql('CREATE TABLE neon_auth.account (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, "accountId" TEXT NOT NULL, "providerId" TEXT NOT NULL, "userId" UUID NOT NULL, "accessToken" TEXT DEFAULT NULL, "refreshToken" TEXT DEFAULT NULL, "idToken" TEXT DEFAULT NULL, "accessTokenExpiresAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, "refreshTokenExpiresAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, scope TEXT DEFAULT NULL, password TEXT DEFAULT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, "updatedAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX "account_userId_idx" ON neon_auth.account ("userId")');
        $this->addSql('CREATE TABLE neon_auth.invitation (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, "organizationId" UUID NOT NULL, email TEXT NOT NULL, role TEXT DEFAULT NULL, status TEXT NOT NULL, "expiresAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, "inviterId" UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX invitation_email_idx ON neon_auth.invitation (email)');
        $this->addSql('CREATE INDEX "invitation_organizationId_idx" ON neon_auth.invitation ("organizationId")');
        $this->addSql('CREATE INDEX IDX_E8EB94DD3D8E5A2 ON neon_auth.invitation ("inviterId")');
        $this->addSql('CREATE TABLE neon_auth.jwks (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, "publicKey" TEXT NOT NULL, "privateKey" TEXT NOT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, "expiresAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE neon_auth.member (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, "organizationId" UUID NOT NULL, "userId" UUID NOT NULL, role TEXT NOT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX "member_userId_idx" ON neon_auth.member ("userId")');
        $this->addSql('CREATE INDEX "member_organizationId_idx" ON neon_auth.member ("organizationId")');
        $this->addSql('CREATE TABLE neon_auth.organization (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, name TEXT NOT NULL, slug TEXT NOT NULL, logo TEXT DEFAULT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, metadata TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX organization_slug_uidx ON neon_auth.organization (slug)');
        $this->addSql('CREATE UNIQUE INDEX organization_slug_key ON neon_auth.organization (slug)');
        $this->addSql('CREATE TABLE neon_auth.project_config (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, name TEXT NOT NULL, endpoint_id TEXT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, trusted_origins JSONB NOT NULL, social_providers JSONB NOT NULL, email_provider JSONB DEFAULT NULL, email_and_password JSONB DEFAULT NULL, allow_localhost BOOLEAN NOT NULL, plugin_configs JSONB DEFAULT NULL, webhook_config JSONB DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX project_config_endpoint_id_key ON neon_auth.project_config (endpoint_id)');
        $this->addSql('CREATE TABLE neon_auth.session (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, "expiresAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, token TEXT NOT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, "updatedAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, "ipAddress" TEXT DEFAULT NULL, "userAgent" TEXT DEFAULT NULL, "userId" UUID NOT NULL, "impersonatedBy" TEXT DEFAULT NULL, "activeOrganizationId" TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX session_token_key ON neon_auth.session (token)');
        $this->addSql('CREATE INDEX "session_userId_idx" ON neon_auth.session ("userId")');
        $this->addSql('CREATE TABLE neon_auth."user" (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL, "emailVerified" BOOLEAN NOT NULL, image TEXT DEFAULT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, "updatedAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, role TEXT DEFAULT NULL, banned BOOLEAN DEFAULT NULL, "banReason" TEXT DEFAULT NULL, "banExpires" TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX user_email_key ON neon_auth."user" (email)');
        $this->addSql('CREATE TABLE neon_auth.verification (id UUID DEFAULT \'gen_random_uuid()\' NOT NULL, identifier TEXT NOT NULL, value TEXT NOT NULL, "expiresAt" TIMESTAMP(0) WITH TIME ZONE NOT NULL, "createdAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, "updatedAt" TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX verification_identifier_idx ON neon_auth.verification (identifier)');
        $this->addSql('ALTER TABLE neon_auth.account ADD CONSTRAINT "account_userId_fkey" FOREIGN KEY ("userId") REFERENCES neon_auth."user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE neon_auth.invitation ADD CONSTRAINT "invitation_organizationId_fkey" FOREIGN KEY ("organizationId") REFERENCES neon_auth.organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE neon_auth.invitation ADD CONSTRAINT "invitation_inviterId_fkey" FOREIGN KEY ("inviterId") REFERENCES neon_auth."user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE neon_auth.member ADD CONSTRAINT "member_organizationId_fkey" FOREIGN KEY ("organizationId") REFERENCES neon_auth.organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE neon_auth.member ADD CONSTRAINT "member_userId_fkey" FOREIGN KEY ("userId") REFERENCES neon_auth."user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE neon_auth.session ADD CONSTRAINT "session_userId_fkey" FOREIGN KEY ("userId") REFERENCES neon_auth."user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
