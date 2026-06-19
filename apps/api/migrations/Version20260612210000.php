<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-1.2 (#1464, ADR-0019) — normalise legacy JSONB value envelopes to the
 * per-AttributeType canon:
 *
 *   select       {value: "<code>"}   -> {option_code: "<code>"}
 *   multiselect  {value: [...]}      -> {option_codes: [...]}
 *   multiselect  bare array [...]    -> {option_codes: [...]}
 *   price        {value: <number>}   -> {amount: <number>} (currency unknown —
 *                                       left absent, the price validator asks
 *                                       for it on the next manual edit)
 *
 * Both `object_values.value` and the denormalised `objects.attributes_indexed`
 * cache (which copies envelopes verbatim) are rewritten. Run
 * `pim:search:reindex` afterwards — Meilisearch documents flatten the same
 * envelopes (deploy note in PR #1505).
 *
 * DESTRUCTIVE / IRREVERSIBLE: the rewrite happens in place; `down()` cannot
 * reconstruct the legacy shapes. A pre-dump MUST be taken before running this
 * migration — see docs/runbook/destructive-migrations.md (AUD-041).
 */
final class Version20260612210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-0019: migrate legacy {value} select/multiselect/price envelopes to canonical shapes (object_values + attributes_indexed)';
    }

    public function up(Schema $schema): void
    {
        // ── object_values ────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            UPDATE object_values ov
            SET value = jsonb_build_object('option_code', ov.value->'value') || (ov.value - 'value')
            FROM attributes a
            WHERE a.id = ov.attribute_id
              AND a.type = 'select'
              AND jsonb_typeof(ov.value) = 'object'
              AND jsonb_exists(ov.value, 'value')
              AND NOT jsonb_exists(ov.value, 'option_code')
              AND jsonb_typeof(ov.value->'value') = 'string'
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE object_values ov
            SET value = jsonb_build_object('option_codes', ov.value->'value') || (ov.value - 'value')
            FROM attributes a
            WHERE a.id = ov.attribute_id
              AND a.type = 'multiselect'
              AND jsonb_typeof(ov.value) = 'object'
              AND jsonb_exists(ov.value, 'value')
              AND NOT jsonb_exists(ov.value, 'option_codes')
              AND jsonb_typeof(ov.value->'value') = 'array'
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE object_values ov
            SET value = jsonb_build_object('option_codes', ov.value)
            FROM attributes a
            WHERE a.id = ov.attribute_id
              AND a.type = 'multiselect'
              AND jsonb_typeof(ov.value) = 'array'
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE object_values ov
            SET value = jsonb_build_object('amount', ov.value->'value') || (ov.value - 'value')
            FROM attributes a
            WHERE a.id = ov.attribute_id
              AND a.type = 'price'
              AND jsonb_typeof(ov.value) = 'object'
              AND jsonb_exists(ov.value, 'value')
              AND NOT jsonb_exists(ov.value, 'amount')
              AND jsonb_typeof(ov.value->'value') = 'number'
        SQL);

        // Edge shapes found in dev data: price {value: "100"} (numeric string)
        // and multiselect {value: "single"} (bare string member).
        $this->addSql(<<<'SQL'
            UPDATE object_values ov
            SET value = jsonb_build_object('amount', (ov.value->>'value')::numeric) || (ov.value - 'value')
            FROM attributes a
            WHERE a.id = ov.attribute_id
              AND a.type = 'price'
              AND jsonb_typeof(ov.value) = 'object'
              AND jsonb_exists(ov.value, 'value')
              AND NOT jsonb_exists(ov.value, 'amount')
              AND jsonb_typeof(ov.value->'value') = 'string'
              AND ov.value->>'value' ~ '^-?[0-9]+(\.[0-9]+)?$'
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE object_values ov
            SET value = jsonb_build_object('option_codes', jsonb_build_array(ov.value->'value')) || (ov.value - 'value')
            FROM attributes a
            WHERE a.id = ov.attribute_id
              AND a.type = 'multiselect'
              AND jsonb_typeof(ov.value) = 'object'
              AND jsonb_exists(ov.value, 'value')
              AND NOT jsonb_exists(ov.value, 'option_codes')
              AND jsonb_typeof(ov.value->'value') = 'string'
        SQL);

        // ── objects.attributes_indexed (verbatim envelope copies) ────────
        // One pass per affected attribute code; PL/pgSQL keeps it set-based
        // per code while reusing the attribute -> type mapping.
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE attr RECORD;
            BEGIN
                FOR attr IN
                    SELECT DISTINCT a.code, a.type
                    FROM attributes a
                    WHERE a.type IN ('select', 'multiselect', 'price')
                LOOP
                    IF attr.type = 'select' THEN
                        EXECUTE format(
                            'UPDATE objects SET attributes_indexed = jsonb_set(attributes_indexed, %L,
                                jsonb_build_object(''option_code'', attributes_indexed#>%L) || ((attributes_indexed->%L) - ''value''))
                             WHERE jsonb_typeof(attributes_indexed->%L) = ''object''
                               AND jsonb_exists(attributes_indexed->%L, ''value'')
                               AND NOT jsonb_exists(attributes_indexed->%L, ''option_code'')
                               AND jsonb_typeof(attributes_indexed#>%L) = ''string''',
                            ARRAY[attr.code], ARRAY[attr.code, 'value'], attr.code,
                            attr.code, attr.code, attr.code, ARRAY[attr.code, 'value']
                        );
                    ELSIF attr.type = 'multiselect' THEN
                        EXECUTE format(
                            'UPDATE objects SET attributes_indexed = jsonb_set(attributes_indexed, %L,
                                jsonb_build_object(''option_codes'', attributes_indexed#>%L) || ((attributes_indexed->%L) - ''value''))
                             WHERE jsonb_typeof(attributes_indexed->%L) = ''object''
                               AND jsonb_exists(attributes_indexed->%L, ''value'')
                               AND NOT jsonb_exists(attributes_indexed->%L, ''option_codes'')
                               AND jsonb_typeof(attributes_indexed#>%L) = ''array''',
                            ARRAY[attr.code], ARRAY[attr.code, 'value'], attr.code,
                            attr.code, attr.code, attr.code, ARRAY[attr.code, 'value']
                        );
                        EXECUTE format(
                            'UPDATE objects SET attributes_indexed = jsonb_set(attributes_indexed, %L,
                                jsonb_build_object(''option_codes'', attributes_indexed->%L))
                             WHERE jsonb_typeof(attributes_indexed->%L) = ''array''',
                            ARRAY[attr.code], attr.code, attr.code
                        );
                    ELSIF attr.type = 'price' THEN
                        EXECUTE format(
                            'UPDATE objects SET attributes_indexed = jsonb_set(attributes_indexed, %L,
                                jsonb_build_object(''amount'', attributes_indexed#>%L) || ((attributes_indexed->%L) - ''value''))
                             WHERE jsonb_typeof(attributes_indexed->%L) = ''object''
                               AND jsonb_exists(attributes_indexed->%L, ''value'')
                               AND NOT jsonb_exists(attributes_indexed->%L, ''amount'')
                               AND jsonb_typeof(attributes_indexed#>%L) = ''number''',
                            ARRAY[attr.code], ARRAY[attr.code, 'value'], attr.code,
                            attr.code, attr.code, attr.code, ARRAY[attr.code, 'value']
                        );
                    END IF;
                END LOOP;
            END $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        // AUD-041: do NOT assume a backup exists. The original message named a
        // specific dump file (backups/pre-imp2-1.2-*.dump) as if guaranteed —
        // it is not (the pgBackRest cron was stale; see the runbook). The
        // canonicalisation overwrites the legacy `{value}` envelope in place, so
        // recovery REQUIRES a dump that must be taken BEFORE running this
        // migration. Point the operator at the runbook rather than a phantom file.
        $this->throwIrreversibleMigrationException(
            'ADR-0019 canonicalisation overwrites legacy {value} envelopes in place and is one-way. '
            .'Recovery requires a database dump taken BEFORE this migration ran (pg_dump or a pgBackRest '
            .'snapshot) — there is no automatic backup to assume. See docs/runbook/destructive-migrations.md '
            .'for the pre-dump requirement and the restore procedure.',
        );
    }
}
