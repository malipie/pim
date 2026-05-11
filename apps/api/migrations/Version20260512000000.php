<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-IMP-02 (#498) — adds `code` + `mode` to `import_profiles`.
 *
 * `code` is a stable slug exposed in the wizard + profile cards. It is
 * backfilled from `name` (lower + non-alphanumeric stripped to '-') on
 * existing rows and protected by a UNIQUE (tenant_id, user_id, code)
 * index so two profiles owned by the same user cannot collide.
 *
 * `mode` follows the design's ImportMode enum (ADD / UPDATE / UPSERT /
 * MERGE / INCREMENT / DELETE). Backfill default is 'UPDATE' which
 * matches the wizard's existing behaviour on submit.
 */
final class Version20260512000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-IMP-02: import_profiles.code + import_profiles.mode + unique (tenant_id, user_id, code).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE import_profiles ADD COLUMN code VARCHAR(64)");
        $this->addSql("ALTER TABLE import_profiles ADD COLUMN mode VARCHAR(16) NOT NULL DEFAULT 'UPDATE'");
        // Backfill code from name. Strategy:
        //   1. lower + replace non-alnum with '-'
        //   2. collapse repeated '-'
        //   3. trim leading/trailing '-'
        //   4. prefix `id` first 8 chars on collision (safety net)
        $this->addSql(<<<'SQL'
UPDATE import_profiles
SET code = TRIM(
    BOTH '-'
    FROM REGEXP_REPLACE(
        REGEXP_REPLACE(LOWER(name), '[^a-z0-9]+', '-', 'g'),
        '-+', '-', 'g'
    )
)
WHERE code IS NULL
SQL);
        // Safety net for the rare empty/duplicate case: append id prefix.
        $this->addSql(<<<'SQL'
UPDATE import_profiles
SET code = COALESCE(NULLIF(code, ''), 'profile') || '-' || SUBSTRING(id::text FROM 1 FOR 8)
WHERE code IS NULL OR code = '' OR id::text IN (
    SELECT MIN(id::text) FROM (
        SELECT id, code, tenant_id, user_id, ROW_NUMBER() OVER (PARTITION BY tenant_id, user_id, code ORDER BY created_at) AS rn
        FROM import_profiles
    ) dupes WHERE rn > 1 GROUP BY id, code, tenant_id, user_id
)
SQL);
        $this->addSql('ALTER TABLE import_profiles ALTER COLUMN code SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX import_profiles_tenant_user_code_uniq ON import_profiles (tenant_id, user_id, code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS import_profiles_tenant_user_code_uniq');
        $this->addSql('ALTER TABLE import_profiles DROP COLUMN IF EXISTS mode');
        $this->addSql('ALTER TABLE import_profiles DROP COLUMN IF EXISTS code');
    }
}
