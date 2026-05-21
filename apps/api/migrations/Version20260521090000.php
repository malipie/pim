<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Locales feature (#869, LOC-01) — schema foundation.
 *
 * Extends the global `locales` catalog with ISO 639-1 + ISO 3166 metadata
 * (language, region, display_name JSONB, is_popular) and seeds the popular
 * CEE+DACH subset (14 locales flagged is_popular=true) plus ~30 additional
 * globally relevant locales.
 *
 * Creates per-tenant `tenant_locales` with default / mandatory / fallback /
 * sort_order / soft-delete state. Partial unique index enforces exactly one
 * `is_default=true` row per tenant.
 *
 * Refactors `channel_locales` to carry an explicit `tenant_id` in the PK so
 * Postgres RLS and the Doctrine tenant filter can scope it without joining
 * back to `channels`.
 */
final class Version20260521090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Locales feature (#869): ISO catalog + tenant_locales + channel_locales tenant_id PK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE locales ADD COLUMN IF NOT EXISTS language VARCHAR(8) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE locales ADD COLUMN IF NOT EXISTS region VARCHAR(8) DEFAULT NULL");
        $this->addSql("ALTER TABLE locales ADD COLUMN IF NOT EXISTS display_name JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("ALTER TABLE locales ADD COLUMN IF NOT EXISTS is_popular BOOLEAN NOT NULL DEFAULT false");

        $this->seedLocaleCatalog();

        $this->addSql("ALTER TABLE locales ALTER COLUMN language DROP DEFAULT");

        $this->addSql('
            CREATE TABLE IF NOT EXISTS tenant_locales (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                locale_id UUID NOT NULL,
                is_default BOOLEAN NOT NULL DEFAULT false,
                is_mandatory BOOLEAN NOT NULL DEFAULT false,
                fallback_locale_id UUID DEFAULT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT tenant_locales_tenant_locale_uniq UNIQUE (tenant_id, locale_id),
                CONSTRAINT tenant_locales_tenant_fk FOREIGN KEY (tenant_id)
                    REFERENCES tenants (id) ON DELETE CASCADE,
                CONSTRAINT tenant_locales_locale_fk FOREIGN KEY (locale_id)
                    REFERENCES locales (id) ON DELETE RESTRICT,
                CONSTRAINT tenant_locales_fallback_fk FOREIGN KEY (fallback_locale_id)
                    REFERENCES locales (id) ON DELETE SET NULL,
                CONSTRAINT tenant_locales_no_self_fallback CHECK (fallback_locale_id IS NULL OR fallback_locale_id <> locale_id)
            )
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS tenant_locales_tenant_idx ON tenant_locales (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS tenant_locales_one_default_per_tenant
            ON tenant_locales (tenant_id) WHERE is_default = true');
        $this->addSql('CREATE INDEX IF NOT EXISTS tenant_locales_active_idx
            ON tenant_locales (tenant_id, is_active)');

        $this->addSql('ALTER TABLE channel_locales ADD COLUMN IF NOT EXISTS tenant_id UUID');
        $this->addSql('
            UPDATE channel_locales cl
            SET tenant_id = c.tenant_id
            FROM channels c
            WHERE c.id = cl.channel_id AND cl.tenant_id IS NULL
        ');
        $this->addSql('ALTER TABLE channel_locales ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT IF EXISTS channel_locales_pkey');
        $this->addSql('ALTER TABLE channel_locales
            ADD CONSTRAINT channel_locales_pkey PRIMARY KEY (tenant_id, channel_id, locale_id)');
        $this->addSql('
            ALTER TABLE channel_locales
            ADD CONSTRAINT channel_locales_tenant_fk FOREIGN KEY (tenant_id)
                REFERENCES tenants (id) ON DELETE CASCADE
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS channel_locales_tenant_idx ON channel_locales (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT IF EXISTS channel_locales_tenant_fk');
        $this->addSql('DROP INDEX IF EXISTS channel_locales_tenant_idx');
        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT IF EXISTS channel_locales_pkey');
        $this->addSql('ALTER TABLE channel_locales
            ADD CONSTRAINT channel_locales_pkey PRIMARY KEY (channel_id, locale_id)');
        $this->addSql('ALTER TABLE channel_locales DROP COLUMN IF EXISTS tenant_id');

        $this->addSql('DROP TABLE IF EXISTS tenant_locales');

        $this->addSql('ALTER TABLE locales DROP COLUMN IF EXISTS is_popular');
        $this->addSql('ALTER TABLE locales DROP COLUMN IF EXISTS display_name');
        $this->addSql('ALTER TABLE locales DROP COLUMN IF EXISTS region');
        $this->addSql('ALTER TABLE locales DROP COLUMN IF EXISTS language');
    }

    /**
     * Seed the ISO catalog. The two pre-existing rows (`pl_PL`, `en_US`)
     * get backfilled in-place; the rest are inserted with ON CONFLICT DO
     * NOTHING so re-running the migration on a partially-seeded DB is a
     * no-op.
     */
    private function seedLocaleCatalog(): void
    {
        foreach (self::CATALOG as [$code, $language, $region, $pl, $en, $popular]) {
            $popularLiteral = $popular ? 'true' : 'false';
            $displayJson = json_encode(['pl' => $pl, 'en' => $en], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $this->addSql(sprintf(
                "UPDATE locales SET language = %s, region = %s, display_name = %s::jsonb, is_popular = %s WHERE code = %s",
                $this->quote($language),
                null === $region ? 'NULL' : $this->quote($region),
                $this->quote($displayJson),
                $popularLiteral,
                $this->quote($code),
            ));

            $labelForLegacy = $pl;
            $this->addSql(sprintf(
                "INSERT INTO locales (id, code, label, language, region, display_name, is_popular)
                 SELECT gen_random_uuid(), %s, %s, %s, %s, %s::jsonb, %s
                 WHERE NOT EXISTS (SELECT 1 FROM locales WHERE code = %s)",
                $this->quote($code),
                $this->quote($labelForLegacy),
                $this->quote($language),
                null === $region ? 'NULL' : $this->quote($region),
                $this->quote($displayJson),
                $popularLiteral,
                $this->quote($code),
            ));
        }
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @var list<array{0:string,1:string,2:?string,3:string,4:string,5:bool}>
     */
    private const CATALOG = [
        ['pl_PL', 'pl', 'PL', 'Polski (Polska)', 'Polish (Poland)', true],
        ['en_US', 'en', 'US', 'Angielski (USA)', 'English (United States)', true],
        ['en_GB', 'en', 'GB', 'Angielski (Wielka Brytania)', 'English (United Kingdom)', true],
        ['de_DE', 'de', 'DE', 'Niemiecki (Niemcy)', 'German (Germany)', true],
        ['de_AT', 'de', 'AT', 'Niemiecki (Austria)', 'German (Austria)', true],
        ['de_CH', 'de', 'CH', 'Niemiecki (Szwajcaria)', 'German (Switzerland)', true],
        ['fr_FR', 'fr', 'FR', 'Francuski (Francja)', 'French (France)', true],
        ['it_IT', 'it', 'IT', 'Włoski (Włochy)', 'Italian (Italy)', true],
        ['es_ES', 'es', 'ES', 'Hiszpański (Hiszpania)', 'Spanish (Spain)', true],
        ['cs_CZ', 'cs', 'CZ', 'Czeski (Czechy)', 'Czech (Czechia)', true],
        ['sk_SK', 'sk', 'SK', 'Słowacki (Słowacja)', 'Slovak (Slovakia)', true],
        ['hu_HU', 'hu', 'HU', 'Węgierski (Węgry)', 'Hungarian (Hungary)', true],
        ['ro_RO', 'ro', 'RO', 'Rumuński (Rumunia)', 'Romanian (Romania)', true],
        ['nl_NL', 'nl', 'NL', 'Holenderski (Holandia)', 'Dutch (Netherlands)', true],

        ['en_CA', 'en', 'CA', 'Angielski (Kanada)', 'English (Canada)', false],
        ['en_AU', 'en', 'AU', 'Angielski (Australia)', 'English (Australia)', false],
        ['en_IE', 'en', 'IE', 'Angielski (Irlandia)', 'English (Ireland)', false],
        ['fr_BE', 'fr', 'BE', 'Francuski (Belgia)', 'French (Belgium)', false],
        ['fr_CA', 'fr', 'CA', 'Francuski (Kanada)', 'French (Canada)', false],
        ['fr_CH', 'fr', 'CH', 'Francuski (Szwajcaria)', 'French (Switzerland)', false],
        ['nl_BE', 'nl', 'BE', 'Holenderski (Belgia)', 'Dutch (Belgium)', false],
        ['pt_PT', 'pt', 'PT', 'Portugalski (Portugalia)', 'Portuguese (Portugal)', false],
        ['pt_BR', 'pt', 'BR', 'Portugalski (Brazylia)', 'Portuguese (Brazil)', false],
        ['es_MX', 'es', 'MX', 'Hiszpański (Meksyk)', 'Spanish (Mexico)', false],
        ['es_AR', 'es', 'AR', 'Hiszpański (Argentyna)', 'Spanish (Argentina)', false],
        ['it_CH', 'it', 'CH', 'Włoski (Szwajcaria)', 'Italian (Switzerland)', false],
        ['da_DK', 'da', 'DK', 'Duński (Dania)', 'Danish (Denmark)', false],
        ['sv_SE', 'sv', 'SE', 'Szwedzki (Szwecja)', 'Swedish (Sweden)', false],
        ['no_NO', 'no', 'NO', 'Norweski (Norwegia)', 'Norwegian (Norway)', false],
        ['fi_FI', 'fi', 'FI', 'Fiński (Finlandia)', 'Finnish (Finland)', false],
        ['is_IS', 'is', 'IS', 'Islandzki (Islandia)', 'Icelandic (Iceland)', false],
        ['et_EE', 'et', 'EE', 'Estoński (Estonia)', 'Estonian (Estonia)', false],
        ['lt_LT', 'lt', 'LT', 'Litewski (Litwa)', 'Lithuanian (Lithuania)', false],
        ['lv_LV', 'lv', 'LV', 'Łotewski (Łotwa)', 'Latvian (Latvia)', false],
        ['ru_RU', 'ru', 'RU', 'Rosyjski (Rosja)', 'Russian (Russia)', false],
        ['uk_UA', 'uk', 'UA', 'Ukraiński (Ukraina)', 'Ukrainian (Ukraine)', false],
        ['bg_BG', 'bg', 'BG', 'Bułgarski (Bułgaria)', 'Bulgarian (Bulgaria)', false],
        ['hr_HR', 'hr', 'HR', 'Chorwacki (Chorwacja)', 'Croatian (Croatia)', false],
        ['sr_RS', 'sr', 'RS', 'Serbski (Serbia)', 'Serbian (Serbia)', false],
        ['sl_SI', 'sl', 'SI', 'Słoweński (Słowenia)', 'Slovenian (Slovenia)', false],
        ['el_GR', 'el', 'GR', 'Grecki (Grecja)', 'Greek (Greece)', false],
        ['tr_TR', 'tr', 'TR', 'Turecki (Turcja)', 'Turkish (Turkey)', false],
        ['ar_SA', 'ar', 'SA', 'Arabski (Arabia Saudyjska)', 'Arabic (Saudi Arabia)', false],
        ['he_IL', 'he', 'IL', 'Hebrajski (Izrael)', 'Hebrew (Israel)', false],
        ['ja_JP', 'ja', 'JP', 'Japoński (Japonia)', 'Japanese (Japan)', false],
        ['ko_KR', 'ko', 'KR', 'Koreański (Korea Pd.)', 'Korean (South Korea)', false],
        ['zh_CN', 'zh', 'CN', 'Chiński (uproszczony)', 'Chinese (Simplified)', false],
        ['zh_TW', 'zh', 'TW', 'Chiński (tradycyjny)', 'Chinese (Traditional)', false],
    ];
}
