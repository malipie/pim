<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #36 — Channel + Locale + Currency + ChannelObjectTypeMapping.
 *
 * Five new tables:
 *   - `locales`, `currencies` — global infrastructure rows shared across
 *     tenants (BCP-47 codes, ISO 4217). Seeded inline with the MVP set
 *     (`pl_PL`, `en_US`; `PLN`, `EUR`, `USD`).
 *   - `channels` — tenant-scoped sales / publication channel.
 *   - `channel_locales`, `channel_currencies` — M2M opt-in junction.
 *   - `channel_object_type_mappings` — per-channel × per-ObjectType ×
 *     per-Attribute target field mapping (replaces pre-ADR-009
 *     `ChannelAttributeMapping`).
 *
 * `channels.category_tree_root_object_id` is a soft FK on `objects.id`
 * — the kind discriminator (`kind = 'category'`) is enforced by the
 * Doctrine listener `ChannelCategoryRootValidator`, not by a CHECK,
 * because Postgres CHECK on an FK target column can't reach the
 * referenced row.
 */
final class Version20260429064833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channels + locales + currencies + channel_object_type_mappings (#36).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE locales (
              id UUID NOT NULL,
              code VARCHAR(16) NOT NULL,
              label VARCHAR(64) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX locales_code_uniq ON locales (code)');

        $this->addSql(<<<'SQL'
            CREATE TABLE currencies (
              id UUID NOT NULL,
              code VARCHAR(8) NOT NULL,
              symbol VARCHAR(8) NOT NULL,
              label VARCHAR(64) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX currencies_code_uniq ON currencies (code)');

        $this->addSql(<<<'SQL'
            CREATE TABLE channels (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              label JSONB NOT NULL,
              category_tree_root_object_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX channels_tenant_idx ON channels (tenant_id)');
        $this->addSql('CREATE INDEX channels_category_tree_root_idx ON channels (category_tree_root_object_id)');
        $this->addSql('CREATE UNIQUE INDEX channels_tenant_code_uniq ON channels (tenant_id, code)');
        $this->addSql('ALTER TABLE channels ADD CONSTRAINT channels_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channels ADD CONSTRAINT channels_category_root_fk FOREIGN KEY (category_tree_root_object_id) REFERENCES objects (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE channel_locales (
              channel_id UUID NOT NULL,
              locale_id UUID NOT NULL,
              PRIMARY KEY (channel_id, locale_id)
            )
        SQL);
        $this->addSql('CREATE INDEX channel_locales_channel_idx ON channel_locales (channel_id)');
        $this->addSql('CREATE INDEX channel_locales_locale_idx ON channel_locales (locale_id)');
        $this->addSql('ALTER TABLE channel_locales ADD CONSTRAINT channel_locales_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_locales ADD CONSTRAINT channel_locales_locale_fk FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE channel_currencies (
              channel_id UUID NOT NULL,
              currency_id UUID NOT NULL,
              PRIMARY KEY (channel_id, currency_id)
            )
        SQL);
        $this->addSql('CREATE INDEX channel_currencies_channel_idx ON channel_currencies (channel_id)');
        $this->addSql('CREATE INDEX channel_currencies_currency_idx ON channel_currencies (currency_id)');
        $this->addSql('ALTER TABLE channel_currencies ADD CONSTRAINT channel_currencies_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_currencies ADD CONSTRAINT channel_currencies_currency_fk FOREIGN KEY (currency_id) REFERENCES currencies (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE channel_object_type_mappings (
              id UUID NOT NULL,
              channel_id UUID NOT NULL,
              object_type_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              target_field VARCHAR(255) NOT NULL,
              is_published BOOLEAN DEFAULT true NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX channel_object_type_mappings_channel_idx ON channel_object_type_mappings (channel_id)');
        $this->addSql('CREATE INDEX channel_object_type_mappings_object_type_idx ON channel_object_type_mappings (object_type_id)');
        $this->addSql('CREATE INDEX channel_object_type_mappings_attribute_idx ON channel_object_type_mappings (attribute_id)');
        $this->addSql('CREATE UNIQUE INDEX channel_object_type_mappings_triple_uniq ON channel_object_type_mappings (channel_id, object_type_id, attribute_id)');
        $this->addSql('ALTER TABLE channel_object_type_mappings ADD CONSTRAINT channel_object_type_mappings_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_object_type_mappings ADD CONSTRAINT channel_object_type_mappings_object_type_fk FOREIGN KEY (object_type_id) REFERENCES object_types (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_object_type_mappings ADD CONSTRAINT channel_object_type_mappings_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE NOT DEFERRABLE');

        // Seed default global locales + currencies. Idempotent — re-runs
        // on already-seeded DB are no-ops via WHERE NOT EXISTS.
        $this->seedLocale('pl_PL', 'Polski (Polska)');
        $this->seedLocale('en_US', 'English (United States)');
        $this->seedCurrency('PLN', 'zł', 'Polish złoty');
        $this->seedCurrency('EUR', '€', 'Euro');
        $this->seedCurrency('USD', '$', 'United States dollar');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel_object_type_mappings DROP CONSTRAINT channel_object_type_mappings_attribute_fk');
        $this->addSql('ALTER TABLE channel_object_type_mappings DROP CONSTRAINT channel_object_type_mappings_object_type_fk');
        $this->addSql('ALTER TABLE channel_object_type_mappings DROP CONSTRAINT channel_object_type_mappings_channel_fk');
        $this->addSql('DROP TABLE channel_object_type_mappings');

        $this->addSql('ALTER TABLE channel_currencies DROP CONSTRAINT channel_currencies_currency_fk');
        $this->addSql('ALTER TABLE channel_currencies DROP CONSTRAINT channel_currencies_channel_fk');
        $this->addSql('DROP TABLE channel_currencies');

        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT channel_locales_locale_fk');
        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT channel_locales_channel_fk');
        $this->addSql('DROP TABLE channel_locales');

        $this->addSql('ALTER TABLE channels DROP CONSTRAINT channels_category_root_fk');
        $this->addSql('ALTER TABLE channels DROP CONSTRAINT channels_tenant_fk');
        $this->addSql('DROP TABLE channels');

        $this->addSql('DROP TABLE currencies');
        $this->addSql('DROP TABLE locales');
    }

    private function seedLocale(string $code, string $label): void
    {
        $this->addSql(\sprintf(
            "INSERT INTO locales (id, code, label) SELECT gen_random_uuid(), '%s', '%s' WHERE NOT EXISTS (SELECT 1 FROM locales WHERE code = '%s')",
            $code,
            addslashes($label),
            $code,
        ));
    }

    private function seedCurrency(string $code, string $symbol, string $label): void
    {
        $this->addSql(\sprintf(
            "INSERT INTO currencies (id, code, symbol, label) SELECT gen_random_uuid(), '%s', '%s', '%s' WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = '%s')",
            $code,
            addslashes($symbol),
            addslashes($label),
            $code,
        ));
    }
}
