<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1179 — `identifier` attribute type with DB-enforced per-ObjectType
 * value uniqueness (EAN-13, GTIN-14, ISBN, internal SKU).
 *
 * The canonical value lives in `object_values.value->>'value'` (JSONB),
 * which cannot back a plain unique index, and `object_values` has no
 * `object_type_id` column. So we denormalise two trigger-maintained
 * columns and put a partial unique index on them:
 *
 *   - `identifier_value`            — mirror of `value->>'value'`
 *   - `identifier_object_type_id`   — the owning object's ObjectType
 *
 * A `BEFORE INSERT OR UPDATE` trigger populates both columns only when
 * the row's attribute is of type `identifier` (NULL otherwise), so the
 * partial unique index `WHERE identifier_value IS NOT NULL` covers only
 * identifier rows. The trigger — not the application — is the source of
 * truth, so every write path (API, import, seeder, future agent) is
 * covered. Doctrine never writes the columns (they are unmapped).
 *
 * Uniqueness scope is `(tenant_id, object_type, attribute, value)`:
 * an EAN is unique among all objects of one ObjectType for one
 * identifier attribute; the same value may legitimately exist under a
 * different ObjectType or a different identifier attribute.
 */
final class Version20260531120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1179: identifier attribute type — denormalised columns + trigger + per-ObjectType unique index on object_values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_values ADD COLUMN identifier_value VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE object_values ADD COLUMN identifier_object_type_id UUID DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION object_values_sync_identifier() RETURNS trigger AS $$
            DECLARE
                v_type text;
                v_object_type uuid;
            BEGIN
                SELECT type INTO v_type FROM attributes WHERE id = NEW.attribute_id;
                IF v_type = 'identifier' THEN
                    SELECT object_type_id INTO v_object_type FROM objects WHERE id = NEW.object_id;
                    NEW.identifier_value := NEW.value->>'value';
                    NEW.identifier_object_type_id := v_object_type;
                ELSE
                    NEW.identifier_value := NULL;
                    NEW.identifier_object_type_id := NULL;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER object_values_sync_identifier_trg
                BEFORE INSERT OR UPDATE ON object_values
                FOR EACH ROW EXECUTE FUNCTION object_values_sync_identifier();
            SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX object_values_identifier_uniq
                ON object_values (tenant_id, identifier_object_type_id, attribute_id, identifier_value)
                WHERE identifier_value IS NOT NULL;
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS object_values_identifier_uniq');
        $this->addSql('DROP TRIGGER IF EXISTS object_values_sync_identifier_trg ON object_values');
        $this->addSql('DROP FUNCTION IF EXISTS object_values_sync_identifier()');
        $this->addSql('ALTER TABLE object_values DROP COLUMN identifier_object_type_id');
        $this->addSql('ALTER TABLE object_values DROP COLUMN identifier_value');
    }
}
