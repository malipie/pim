<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MOD-10 (#902) — revert the "Brand as 4-th built-in ObjectType"
 * decision from ADR-012. ADR-014 §6 (Decyzje) puts Brand back into
 * tenant-territory: it can be a `select` attribute, a custom ObjectType,
 * or sourced from an external system. The built-in pool is reduced to
 * Product / Category / Asset (matching the pre-ADR-012 ADR-009 contract).
 *
 * Per existing tenant the migration:
 *   - looks up `object_types` row with `kind='brand'`;
 *   - counts referencing `objects` (matched by `object_type_id`);
 *   - **if 0 instances** — DELETE the ObjectType + cascade dependent
 *     junction rows (`object_type_attributes`,
 *     `object_type_attribute_groups`, `category_attribute_groups` with
 *     `target_object_type_id`);
 *   - **if any instances exist** — convert to a custom ObjectType:
 *     `is_built_in=false`, `code_immutable=false`, `deletable=true`.
 *     Operator can now rename, repurpose, or delete the kind through the
 *     standard `/api/object_types/{id}` flow.
 *
 * `ObjectKind::Brand` enum case stays for backward compatibility with
 * stored rows. Down path recreates the seeded built-in row but does NOT
 * re-attach any junction that the down-pass deletion may have wiped —
 * the deletion path is the documented one-way migration.
 */
final class Version20260524120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MOD-10 (#902): demote built-in Brand ObjectType to tenant-custom (or delete when unused).';
    }

    public function up(Schema $schema): void
    {
        // Step 1 — convert Brand ObjectTypes with at least one instance.
        $this->addSql(<<<'SQL'
            UPDATE object_types ot
            SET is_built_in = false, code_immutable = false, deletable = true
            WHERE ot.kind = 'brand'
              AND EXISTS (
                  SELECT 1 FROM objects o WHERE o.object_type_id = ot.id
              )
        SQL);

        // Step 2 — delete unused Brand ObjectTypes. Dependent junction
        // rows cascade through ON DELETE CASCADE on `object_type_id` /
        // `target_object_type_id` (set up in earlier migrations).
        // Defensive: explicitly remove junctions in case CASCADE differs.
        $this->addSql(<<<'SQL'
            DELETE FROM object_type_attributes
            WHERE object_type_id IN (
                SELECT id FROM object_types WHERE kind = 'brand' AND is_built_in = true
            )
        SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM object_type_attribute_groups
            WHERE object_type_id IN (
                SELECT id FROM object_types WHERE kind = 'brand' AND is_built_in = true
            )
        SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM category_attribute_groups
            WHERE target_object_type_id IN (
                SELECT id FROM object_types WHERE kind = 'brand' AND is_built_in = true
            )
        SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM object_types
            WHERE kind = 'brand' AND is_built_in = true
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Restore Brand as a built-in ObjectType per tenant — same shape as
        // Version20260501110000. Tenants whose Brand was demoted to custom
        // keep their custom row alongside the re-seeded built-in (the
        // tenant_code_uniq index allows it because the custom row may have
        // been re-coded). For tenants where Brand was outright deleted,
        // this rebuilds the row with the original seed values.
        $this->addSql(<<<'SQL'
            INSERT INTO object_types (
                id, tenant_id, code, kind, is_built_in, code_immutable, deletable,
                icon, color, label, completeness_rules, hierarchical, has_variants,
                abstract, expose_to_main_menu, is_categorizable, allowed_parent_type_ids,
                schema_version, created_at, updated_at
            )
            SELECT
                gen_random_uuid(), t.id, 'brand', 'brand', true, true, false,
                'Tag', '#F59E0B', '{"pl":"Marka","en":"Brand"}'::jsonb, '{}'::jsonb,
                false, false, false, false, false, '[]'::jsonb,
                1, NOW(), NOW()
            FROM tenants t
            WHERE NOT EXISTS (
                SELECT 1 FROM object_types ot
                WHERE ot.tenant_id = t.id AND ot.code = 'brand'
            )
        SQL);
    }
}
