<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.11 / ticket #107 — BYOK per-tenant Anthropic config.
 *
 * One config per tenant: ciphertext (base64 of nonce ‖ ciphertext ‖
 * tag from libsodium AEAD), the master-key version that produced it
 * (per ADR-0017), the display prefix, and lifecycle timestamps.
 *
 * `tenant_id` is `UNIQUE` so the platform cannot accidentally hold
 * two BYOK rows per tenant. ON DELETE CASCADE — a removed tenant
 * also removes the encrypted secret.
 */
final class Version20260430160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_agent_configs for BYOK Anthropic per-tenant (#107).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_agent_configs (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              anthropic_api_key_encrypted TEXT NOT NULL,
              encryption_key_version INT NOT NULL,
              key_prefix VARCHAR(16) NOT NULL,
              enabled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX tenant_agent_configs_tenant_uniq ON tenant_agent_configs (tenant_id)');
        $this->addSql('ALTER TABLE tenant_agent_configs ADD CONSTRAINT tenant_agent_configs_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_agent_configs DROP CONSTRAINT tenant_agent_configs_tenant_fk');
        $this->addSql('DROP TABLE tenant_agent_configs');
    }
}
