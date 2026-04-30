<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.10 / ticket #90 — ApiProfile + ApiKey tables.
 *
 * `api_profiles` carries the per-integrator projection (visible
 * ObjectTypes + attributes + filters + webhook config). `api_keys`
 * carries the long-lived secret presented on `/api/*` — see ADR-0016
 * for the format and the Argon2id storage choice.
 *
 * `api_keys.key_prefix` is globally unique on purpose: the
 * authenticator (#94) looks up by prefix before the slow Argon2id
 * verify, and a global uniqueness constraint sidesteps a tenant
 * lookup at that point in the request lifecycle.
 */
final class Version20260430120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_profiles + api_keys tables for the API Configurator (#90).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE api_profiles (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              name VARCHAR(128) NOT NULL,
              description TEXT DEFAULT NULL,
              output_format VARCHAR(16) NOT NULL,
              object_type_ids JSONB NOT NULL,
              included_attributes JSONB NOT NULL,
              filters JSONB NOT NULL,
              webhook_url VARCHAR(2048) DEFAULT NULL,
              webhook_events JSONB NOT NULL,
              rate_limit_per_hour INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX api_profiles_tenant_idx ON api_profiles (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX api_profiles_tenant_code_uniq ON api_profiles (tenant_id, code)');
        $this->addSql('ALTER TABLE api_profiles ADD CONSTRAINT api_profiles_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE api_keys (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              key_hash VARCHAR(255) NOT NULL,
              key_prefix VARCHAR(32) NOT NULL,
              name VARCHAR(128) NOT NULL,
              scopes JSONB NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              rate_limit_per_hour INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX api_keys_tenant_idx ON api_keys (tenant_id)');
        $this->addSql('CREATE INDEX api_keys_revoked_at_idx ON api_keys (revoked_at)');
        $this->addSql('CREATE INDEX api_keys_expires_at_idx ON api_keys (expires_at)');
        $this->addSql('CREATE UNIQUE INDEX api_keys_key_prefix_uniq ON api_keys (key_prefix)');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT api_keys_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_keys DROP CONSTRAINT api_keys_tenant_fk');
        $this->addSql('DROP TABLE api_keys');

        $this->addSql('ALTER TABLE api_profiles DROP CONSTRAINT api_profiles_tenant_fk');
        $this->addSql('DROP TABLE api_profiles');
    }
}
