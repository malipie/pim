<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UX-01 — purge legacy `kind='brand'` ObjectType rows.
 *
 * ADR-014 / MOD-10 (#902) reverted Brand as a built-in ObjectKind; the
 * `BuiltInObjectTypeSeeder` stopped creating brand rows months ago, but
 * legacy installations may still carry rows from earlier deploys. This
 * migration removes the `ObjectKind::Brand` enum case altogether (see
 * `apps/api/src/Catalog/Domain/ObjectKind.php`); leaving brand rows in
 * place would break `ObjectKind::from('brand')` at runtime.
 *
 * Cleanup contract:
 *   - delete `object_types` rows where `kind='brand'`
 *   - cascade naturally hits dependent rows via existing FKs
 *     (`objects`, `object_type_attributes`, `object_type_attribute_groups`,
 *     `menu_configurations` items pointing at brand rows). Each of these
 *     FKs declares `ON DELETE CASCADE` already (see Version20260428205215
 *     for `object_types` parent + Version20260504130000 for menu items).
 *   - no-op on fresh installs (zero rows to delete).
 */
final class Version20260526100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UX-01: drop legacy object_types rows with kind=brand (ObjectKind::Brand removed from enum).';
    }

    public function up(Schema $schema): void
    {
        // Single DELETE — Doctrine reports affected count in migration log so
        // legacy deploys see the cleanup, fresh installs see "0 rows" silently.
        $this->addSql(<<<'SQL'
            DELETE FROM object_types WHERE kind = 'brand'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Irreversible — the enum case no longer exists, restoring rows
        // would break ObjectKind::from('brand'). If a rollback is needed,
        // first restore the enum case in a prior migration.
        $this->throwIrreversibleMigrationException(
            'UX-01 brand-kind purge cannot be reverted because ObjectKind::Brand was removed from the enum.',
        );
    }
}
