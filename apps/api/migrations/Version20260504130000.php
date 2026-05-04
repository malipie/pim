<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-08 (#427) — Settings · Menu drag-drop + ObjectType.exposeToMainMenu.
 *
 * Two paired schema additions, neither destructive:
 *
 *   1. `object_types.expose_to_main_menu BOOLEAN NOT NULL DEFAULT FALSE` —
 *      gating flag for main-menu candidacy. Backfill: built-in Product gets
 *      TRUE so the default sidebar reproduces the legacy "Produkty" entry.
 *      Partial index `(tenant_id) WHERE expose_to_main_menu = TRUE` keeps
 *      the lookup cost flat regardless of total ObjectType count per tenant.
 *
 *   2. `menu_configurations` table (singleton per tenant) — stores ordering
 *      + visibility for system items (Pulpit, Multimedia, Workflow, …) and
 *      object_type items (Product + custom ObjectTypes flagged exposeToMainMenu).
 *      `tenant_id` UNIQUE so the row is canonical per tenant; PUT replaces
 *      the items JSONB array atomically. Existing tenants get auto-seeded
 *      on first request (DefaultMenuSeeder); this migration does not
 *      pre-populate rows because we don't have the built-in Product UUID
 *      from a SQL migration context — the seeder does it once it sees
 *      the ObjectType in code.
 *
 * No expand-contract dance: both changes are < 10k rows in any deployment
 * (CLAUDE.md tolerance for in-place migrations).
 */
final class Version20260504130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-08 #427 — ObjectType.expose_to_main_menu + menu_configurations table.';
    }

    public function up(Schema $schema): void
    {
        // --- ObjectType: expose-to-menu gating flag ---
        $this->addSql('ALTER TABLE object_types ADD COLUMN expose_to_main_menu BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql("COMMENT ON COLUMN object_types.expose_to_main_menu IS 'VIEW-08 #427: udostępnij ObjectType jako kandydata do głównego menu (settings/menu)'");

        // Backfill: built-in Product opted-in by default — matches the
        // legacy hard-coded "Produkty" entry in the sidebar so existing
        // deployments do not regress after the migration runs.
        $this->addSql("UPDATE object_types SET expose_to_main_menu = TRUE WHERE kind = 'product' AND is_built_in = TRUE");

        // Partial index: only the rows that participate in menu candidacy
        // hit the index — total cost stays flat as ObjectType count grows.
        $this->addSql('CREATE INDEX idx_object_types_expose_menu ON object_types (tenant_id) WHERE expose_to_main_menu = TRUE');

        // --- MenuConfiguration: singleton per tenant ---
        $this->addSql('CREATE TABLE menu_configurations (
            id UUID PRIMARY KEY,
            tenant_id UUID NOT NULL,
            items JSONB NOT NULL DEFAULT \'[]\'::jsonb,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            CONSTRAINT uniq_menu_config_per_tenant UNIQUE (tenant_id),
            CONSTRAINT fk_menu_config_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        )');
        $this->addSql("COMMENT ON TABLE menu_configurations IS 'VIEW-08 #427: konfiguracja menu głównego per tenant (items JSONB z [{kind, ref, position, visible}])'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS menu_configurations');
        $this->addSql('DROP INDEX IF EXISTS idx_object_types_expose_menu');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS expose_to_main_menu');
    }
}
