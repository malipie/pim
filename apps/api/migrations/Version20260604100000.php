<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1232 — ChannelPublicationProfile entity (ADR-0018).
 *
 * Adds `channel_publication_profiles` table: per (tenant, channel, objectType)
 * allow-list of attributes and locales to publish. `published_attribute_codes
 * = NULL` means publish-all (default).
 *
 * Backfill: creates one default publish-all profile per (channel, objectType)
 * combination already in the database, so existing tenants see no behaviour
 * change. The resolver (#1233) also falls back to publish-all when no row
 * exists, making this backfill defensive-in-depth.
 */
final class Version20260604100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1232: add channel_publication_profiles table + backfill defaults';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE channel_publication_profiles (
                id                       UUID         NOT NULL,
                tenant_id                UUID         NOT NULL,
                channel_id               UUID         NOT NULL,
                object_type_id           UUID         NOT NULL,
                published_attribute_codes JSONB        DEFAULT NULL,
                published_locales        JSONB        NOT NULL DEFAULT '[]',
                column_aliases           JSONB        NOT NULL DEFAULT '{}',
                is_default               BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at               TIMESTAMPTZ  NOT NULL DEFAULT NOW(),

                CONSTRAINT pk_channel_publication_profiles PRIMARY KEY (id),
                CONSTRAINT fk_cpp_tenant  FOREIGN KEY (tenant_id)  REFERENCES tenants(id)      ON DELETE CASCADE,
                CONSTRAINT fk_cpp_channel FOREIGN KEY (channel_id) REFERENCES channels(id)     ON DELETE CASCADE,
                CONSTRAINT uq_cpp_tenant_channel_ot UNIQUE (tenant_id, channel_id, object_type_id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_cpp_channel ON channel_publication_profiles (tenant_id, channel_id)');
        $this->addSql('CREATE INDEX idx_cpp_tenant  ON channel_publication_profiles (tenant_id)');

        // Backfill: one default publish-all profile per existing (channel, objectType).
        // The cross product is intentional — each channel needs a profile row per OT
        // so the operator can later configure per-OT overrides.
        $this->addSql(<<<'SQL'
            INSERT INTO channel_publication_profiles (
                id, tenant_id, channel_id, object_type_id,
                published_attribute_codes, published_locales, column_aliases,
                is_default, created_at
            )
            SELECT
                gen_random_uuid(),
                c.tenant_id,
                c.id,
                ot.id,
                NULL,
                '[]',
                '{}',
                TRUE,
                NOW()
            FROM channels c
            JOIN object_types ot ON ot.tenant_id = c.tenant_id
            ON CONFLICT (tenant_id, channel_id, object_type_id) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS channel_publication_profiles');
    }
}
