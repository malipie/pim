<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Issue #1092 — normalize built-in relation attributes.
 *
 * The 5 seeded relation attributes (`cross_sell`, `up_sell`, `related`,
 * `alternative`, `accessory`) were previously marked `is_system=true` by
 * `BuiltInProductRelationAttributesSeeder::markSystem()`. That made them
 * render a "lock" badge in `/modeling/attributes` and refused detachment
 * via `DetachAttributeFromGroupHandler` — operators could not move them
 * to a custom AttributeGroup or remove them from a product.
 *
 * Operator request: treat these as regular attributes that can be
 * grouped / detached / removed freely. The seeder no longer sets the
 * flag (see same-PR change in BuiltInProductRelationAttributesSeeder);
 * this migration clears the flag on rows already persisted by historic
 * seed runs so existing tenants pick up the new behaviour.
 *
 * Scoped to `type='relation'` + the explicit code list to keep the
 * UPDATE off operator-created attrs that happen to share a name.
 */
final class Version20260528110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Issue #1092 — drop is_system=true from built-in relation attributes (cross_sell, up_sell, related, alternative, accessory).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE attributes
            SET is_system = false
            WHERE type = 'relation'
              AND code IN ('cross_sell', 'up_sell', 'related', 'alternative', 'accessory')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE attributes
            SET is_system = true
            WHERE type = 'relation'
              AND code IN ('cross_sell', 'up_sell', 'related', 'alternative', 'accessory')
        SQL);
    }
}
