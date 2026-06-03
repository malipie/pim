<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UP-05 (#1020) — scope SmartFilterPreset rows per ObjectType via the
 * `resource` column.
 *
 * `resource` is a free-form string mirroring `saved_views.resource` —
 * stores either the `ObjectType.code` (e.g. `samochody`) or NULL.
 * NULL = preset is visible across every kind (legacy semantics for the
 * built-in shipped presets that operate on cross-cutting concerns like
 * completeness). Tenant-side presets created from `UniversalListPage`
 * (UP-06) carry the kind's code so they do not pollute other kinds.
 *
 * Backfill: existing built-in seeded presets target the product list
 * (the only existing consumer pre-UP) so they get `resource = 'products'`
 * — keeps the legacy `/api/products` list looking the same while
 * preventing them from leaking into `/objects/samochody`.
 */
final class Version20260525220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UP-05 (#1020): scope smart_filter_presets per resource (ObjectType.code) for UniversalListPage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE smart_filter_presets
                ADD COLUMN resource VARCHAR(64) NULL
        SQL);

        // Backfill: existing rows are product-list presets — scope them
        // to the legacy `products` resource so `/objects/samochody` does
        // not surface them.
        $this->addSql(<<<'SQL'
            UPDATE smart_filter_presets SET resource = 'products' WHERE resource IS NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX smart_filter_presets_resource_idx
                ON smart_filter_presets (resource)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS smart_filter_presets_resource_idx
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE smart_filter_presets DROP COLUMN resource
        SQL);
    }
}
