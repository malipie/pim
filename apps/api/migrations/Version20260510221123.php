<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic UI-10 / PCAT-01 — Product↔Category junction.
 *
 * Wires products into the existing category-driven attribute-group inheritance
 * pipeline (see `CategoryAttributeGroup` + `EffectiveAttributeGroupResolver`).
 * Until now `EffectiveAttributeGroupResolver::resolve($product)` only returned
 * global ObjectType groups for products — there was no relation to walk for
 * the `kind=Product` branch. PCAT-03 activates that branch on top of this
 * junction.
 *
 * Schema:
 * - Composite PK `(object_id, category_id)` — one row per assignment.
 * - `is_primary BOOLEAN` with a partial unique index that enforces at most
 *   one primary per object (`WHERE is_primary = true`). Cheaper than a row
 *   trigger and atomic with the partial-unique catch + retry pattern in
 *   the upsert path.
 * - `ON DELETE CASCADE` on both FKs to `objects(id)` — when a product OR
 *   a category is removed the assignment row is reaped. Primary repair
 *   for products that lose their primary assignment is handled by
 *   `PrimaryCategoryRepairListener` (PCAT-03), not the DB.
 * - `CHECK (object_id <> category_id)` — defensive, guards against
 *   self-assignment which has no semantic meaning.
 * - No `tenant_id` column. Tenant scope is inherited via the FK to
 *   `objects.tenant_id` (which is `NOT NULL` and TenantScoped). Listed
 *   in `TenantAuditCommand::INFRA_TABLES` allowlist.
 */
final class Version20260510221123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add object_categories junction (PCAT-01 / epic UI-10).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE object_categories (
              object_id UUID NOT NULL,
              category_id UUID NOT NULL,
              is_primary BOOLEAN NOT NULL DEFAULT false,
              position INT NOT NULL DEFAULT 0,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
              PRIMARY KEY (object_id, category_id),
              CONSTRAINT object_categories_no_self CHECK (object_id <> category_id)
            )
        SQL);

        $this->addSql('CREATE INDEX object_categories_object_idx ON object_categories (object_id)');
        $this->addSql('CREATE INDEX object_categories_category_idx ON object_categories (category_id)');

        // Partial unique: at most one primary assignment per object. Lets the
        // app upsert primary swaps as DELETE-of-old + INSERT-of-new inside a
        // single transaction without ever holding two primary rows at flush
        // boundary. NULL primary (no assignments) is legal — the index
        // simply has no row to compare against.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX object_categories_one_primary_per_object
              ON object_categories (object_id) WHERE is_primary = true
        SQL);

        $this->addSql('ALTER TABLE object_categories ADD CONSTRAINT object_categories_object_fk FOREIGN KEY (object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_categories ADD CONSTRAINT object_categories_category_fk FOREIGN KEY (category_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE object_categories');
    }
}
