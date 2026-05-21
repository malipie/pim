<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Locales feature (#870, LOC-02) — backfill `tenant_locales` from the
 * legacy `tenants.enabled_locales` + `tenants.primary_locale` columns
 * introduced by #705.
 *
 * Maps the ISO 639-1 short codes (`pl`, `en`, `de`, …) the legacy
 * `/settings/tenant` form stored to the BCP-47 codes (`pl_PL`, `en_US`,
 * `de_DE`, …) the new `tenant_locales` references. The default `pl` is
 * mapped to `pl_PL` (Polski-Polska), `en` to `en_US`, `de` to `de_DE` —
 * matching the most common regional variant for each language.
 *
 * Decision: legacy columns are *not* dropped. `WorkspaceController`,
 * `TenantConfigController`, and `SuperAdminTenantResponseBuilder` continue
 * to read/write them, so existing UI surfaces (`/settings/tenant`, the
 * Super Admin operator panel) keep working until LOC-07 (#875) refactors
 * the frontend to consume `tenant_locales`. A follow-up ticket will drop
 * the columns once the readers are migrated.
 *
 * Idempotent — `ON CONFLICT (tenant_id, locale_id) DO NOTHING` swallows
 * duplicate rows so re-running the migration after a partial replay leaves
 * `tenant_locales` in the same shape.
 */
final class Version20260522080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Locales (#870): backfill tenant_locales from tenants.enabled_locales + primary_locale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            WITH locale_map(short_code, bcp47) AS (
                VALUES
                    ('pl', 'pl_PL'),
                    ('en', 'en_US'),
                    ('de', 'de_DE'),
                    ('fr', 'fr_FR'),
                    ('it', 'it_IT'),
                    ('es', 'es_ES'),
                    ('cs', 'cs_CZ'),
                    ('sk', 'sk_SK'),
                    ('hu', 'hu_HU'),
                    ('ro', 'ro_RO'),
                    ('nl', 'nl_NL'),
                    ('pt', 'pt_PT'),
                    ('ru', 'ru_RU'),
                    ('uk', 'uk_UA')
            ),
            expanded AS (
                SELECT
                    t.id AS tenant_id,
                    t.primary_locale AS primary_short,
                    elem.value AS short_code,
                    elem.ordinality AS position
                FROM tenants t,
                LATERAL jsonb_array_elements_text(COALESCE(t.enabled_locales, '[]'::jsonb))
                    WITH ORDINALITY AS elem(value, ordinality)
                WHERE t.deleted_at IS NULL
            )
            INSERT INTO tenant_locales (
                id, tenant_id, locale_id,
                is_default, is_mandatory, fallback_locale_id,
                sort_order, is_active, created_at
            )
            SELECT
                gen_random_uuid(),
                e.tenant_id,
                l.id,
                (e.short_code = e.primary_short) AS is_default,
                (e.short_code = e.primary_short) AS is_mandatory,
                NULL,
                (e.position - 1)::int,
                true,
                NOW()
            FROM expanded e
            JOIN locale_map m ON m.short_code = e.short_code
            JOIN locales l ON l.code = m.bcp47
            ON CONFLICT (tenant_id, locale_id) DO NOTHING
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN tenants.enabled_locales IS
                'DEPRECATED (#869–#878): use tenant_locales table. Kept for read-back compatibility until LOC-07 (#875) migrates /settings/tenant.'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN tenants.primary_locale IS
                'DEPRECATED (#869–#878): use tenant_locales.is_default = true row. Kept for read-back compatibility until LOC-07 (#875).'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN tenants.enabled_locales IS NULL");
        $this->addSql("COMMENT ON COLUMN tenants.primary_locale IS NULL");

        $this->addSql(<<<'SQL'
            DELETE FROM tenant_locales
            WHERE tenant_id IN (
                SELECT id FROM tenants
                WHERE enabled_locales IS NOT NULL
                  AND jsonb_array_length(enabled_locales) > 0
            )
        SQL);
    }
}
