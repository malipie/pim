<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-08.3 — System attributes (`is_system`) + auto-attached Audit group (#258).
 *
 * Adds `is_system` to `attributes` and `is_system` (already present as
 * `is_system_group` from #256) materialised through seeded rows. For every
 * tenant we seed:
 *   - 4 system attributes (`created_at`, `updated_at`, `created_by`,
 *     `updated_by`). `created_at`/`updated_at` use the new `datetime`
 *     AttributeType case; `created_by`/`updated_by` use `reference` with
 *     `validation_rules: {target_entity: 'user'}` (per epik plan §12.2).
 *   - 1 system AttributeGroup `audit` (`is_system_group=true,
 *     auto_attached=true`).
 *   - 4 rows in `attribute_group_attributes` wiring the system attrs into
 *     the audit group.
 *   - For every existing `object_types` row (per tenant), an
 *     `object_type_attribute_groups (object_type_id, audit_group_id, 999)`
 *     row so the audit group renders last in the form schema.
 *
 * The runtime counterpart is `BuiltInSystemAttributesSeeder` for tenants
 * created after this migration runs and `AutoAttachAuditGroupListener` for
 * future ObjectTypes (custom kinds in Faza 2/3).
 */
final class Version20260501120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'System attributes (is_system) + auto-attached Audit AttributeGroup (#258).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attributes ADD COLUMN is_system BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('CREATE INDEX attributes_tenant_is_system_idx ON attributes (tenant_id, is_system)');

        // For every existing tenant, seed:
        //   - 4 system attributes
        //   - 1 audit AttributeGroup
        //   - 4 attribute_group_attributes rows
        //   - N object_type_attribute_groups rows (one per existing ObjectType in that tenant)
        //
        // Wrapped in a single statement per tenant via a CTE chain so the
        // INSERTs propagate the freshly minted UUIDs without an intermediate
        // SELECT round-trip from PHP.
        $this->addSql(<<<'SQL'
            WITH ins_attrs AS (
                INSERT INTO attributes (
                    id, tenant_id, code, label, type,
                    is_localizable, is_scopable, is_required, is_system,
                    validation_rules, position, created_at
                )
                SELECT gen_random_uuid(), t.id, def.code,
                       def.label::jsonb, def.type,
                       false, false, false, true,
                       def.validation_rules::jsonb, def.position, NOW()
                FROM tenants t
                CROSS JOIN (VALUES
                    ('created_at', '{"pl":"Utworzono","en":"Created at"}',     'datetime',  '{}',                            1),
                    ('updated_at', '{"pl":"Zmieniono","en":"Updated at"}',     'datetime',  '{}',                            2),
                    ('created_by', '{"pl":"Utworzony przez","en":"Created by"}', 'reference', '{"target_entity":"user"}',    3),
                    ('updated_by', '{"pl":"Zmieniony przez","en":"Updated by"}', 'reference', '{"target_entity":"user"}',    4)
                ) AS def(code, label, type, validation_rules, position)
                WHERE NOT EXISTS (
                    SELECT 1 FROM attributes a
                    WHERE a.tenant_id = t.id AND a.code = def.code
                )
                RETURNING id, tenant_id, code
            )
            SELECT 1 FROM ins_attrs
        SQL);

        // Seed audit AttributeGroup per tenant (idempotent on (tenant_id, code)).
        $this->addSql(<<<'SQL'
            INSERT INTO attribute_groups (
                id, tenant_id, code, label, description, icon, color,
                is_system_group, auto_attached, position, created_at
            )
            SELECT gen_random_uuid(), t.id, 'audit',
                   '{"pl":"Audyt","en":"Audit"}'::jsonb,
                   '{"pl":"Atrybuty systemowe — kto, kiedy.","en":"System attributes — who and when."}'::jsonb,
                   'ShieldCheck', '#64748B',
                   true, true, 999, NOW()
            FROM tenants t
            WHERE NOT EXISTS (
                SELECT 1 FROM attribute_groups g
                WHERE g.tenant_id = t.id AND g.code = 'audit'
            )
        SQL);

        // Wire 4 system attributes into each tenant's audit group.
        $this->addSql(<<<'SQL'
            INSERT INTO attribute_group_attributes (
                attribute_group_id, attribute_id, position, is_required_in_group, visible_when
            )
            SELECT g.id, a.id,
                   CASE a.code
                       WHEN 'created_at' THEN 1
                       WHEN 'updated_at' THEN 2
                       WHEN 'created_by' THEN 3
                       WHEN 'updated_by' THEN 4
                   END,
                   false, NULL
            FROM attribute_groups g
            JOIN attributes a ON a.tenant_id = g.tenant_id
            WHERE g.code = 'audit'
              AND g.is_system_group = true
              AND a.is_system = true
              AND a.code IN ('created_at', 'updated_at', 'created_by', 'updated_by')
              AND NOT EXISTS (
                  SELECT 1 FROM attribute_group_attributes j
                  WHERE j.attribute_group_id = g.id AND j.attribute_id = a.id
              )
        SQL);

        // Auto-attach audit group to every existing ObjectType, position 999
        // so it always renders last in the form schema.
        $this->addSql(<<<'SQL'
            INSERT INTO object_type_attribute_groups (
                object_type_id, attribute_group_id, position
            )
            SELECT ot.id, g.id, 999
            FROM object_types ot
            JOIN attribute_groups g
              ON g.tenant_id = ot.tenant_id
             AND g.code = 'audit'
             AND g.is_system_group = true
            WHERE NOT EXISTS (
                SELECT 1 FROM object_type_attribute_groups oag
                WHERE oag.object_type_id = ot.id
                  AND oag.attribute_group_id = g.id
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM object_type_attribute_groups
            WHERE attribute_group_id IN (
                SELECT id FROM attribute_groups WHERE code = 'audit' AND is_system_group = true
            )
        SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM attribute_group_attributes
            WHERE attribute_group_id IN (
                SELECT id FROM attribute_groups WHERE code = 'audit' AND is_system_group = true
            )
        SQL);
        $this->addSql("DELETE FROM attribute_groups WHERE code = 'audit' AND is_system_group = true");
        $this->addSql("DELETE FROM attributes WHERE is_system = true AND code IN ('created_at', 'updated_at', 'created_by', 'updated_by')");
        $this->addSql('DROP INDEX IF EXISTS attributes_tenant_is_system_idx');
        $this->addSql('ALTER TABLE attributes DROP COLUMN is_system');
    }
}
