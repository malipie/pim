<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-08.2 — ObjectType built-in flags + brand as 4-th built-in (#257).
 *
 * Adds `code_immutable`, `deletable`, `icon`, `color` columns on
 * object_types (`is_built_in` already exists from #33). Marks the existing
 * three built-ins (product / category / asset) as code-immutable +
 * undeletable in line with the platform-owned semantics introduced by
 * ADR-009 and reaffirmed in epik-08-modelowanie §3.4. Seeds `brand` as a
 * 4-th built-in (per `Project Plan/UI/epik-08-modelowanie.md` §3.4 — brand
 * has its own DAM-like footprint and integrations bind to it directly).
 */
final class Version20260501110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ObjectType built-in flags (code_immutable/deletable/icon/color) + brand 4-th built-in (#257).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_types ADD COLUMN code_immutable BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE object_types ADD COLUMN deletable BOOLEAN NOT NULL DEFAULT true');
        $this->addSql('ALTER TABLE object_types ADD COLUMN icon VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE object_types ADD COLUMN color VARCHAR(16) DEFAULT NULL');

        // Lock existing 3 built-ins (product, category, asset).
        $this->addSql(<<<'SQL'
            UPDATE object_types
            SET code_immutable = true, deletable = false,
                icon = CASE kind
                    WHEN 'product' THEN 'Package'
                    WHEN 'category' THEN 'FolderTree'
                    WHEN 'asset' THEN 'Image'
                END,
                color = CASE kind
                    WHEN 'product' THEN '#3B82F6'
                    WHEN 'category' THEN '#10B981'
                    WHEN 'asset' THEN '#8B5CF6'
                END
            WHERE is_built_in = true AND kind IN ('product', 'category', 'asset')
        SQL);

        // Seed brand as 4-th built-in per tenant.
        $this->addSql(<<<'SQL'
            INSERT INTO object_types (
                id, tenant_id, code, kind, is_built_in, code_immutable, deletable,
                icon, color, label, completeness_rules, schema_version,
                created_at, updated_at
            )
            SELECT gen_random_uuid(), t.id, 'brand', 'brand', true, true, false,
                   'Tag', '#F59E0B',
                   '{"pl":"Marka","en":"Brand"}'::jsonb, '{}'::jsonb, 1,
                   NOW(), NOW()
            FROM tenants t
            WHERE NOT EXISTS (
                SELECT 1 FROM object_types o
                WHERE o.tenant_id = t.id AND o.kind = 'brand' AND o.is_built_in = true
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM object_types WHERE kind = 'brand' AND is_built_in = true");
        $this->addSql('ALTER TABLE object_types DROP COLUMN color');
        $this->addSql('ALTER TABLE object_types DROP COLUMN icon');
        $this->addSql('ALTER TABLE object_types DROP COLUMN deletable');
        $this->addSql('ALTER TABLE object_types DROP COLUMN code_immutable');
    }
}
