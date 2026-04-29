<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #35 — AssociationType + Association tables.
 *
 * Per ADR-009 the association links any two `CatalogObject` rows (any
 * `kind`), not just products. Tables:
 *
 *   - `association_types`: tenant-defined classification rows. Every
 *     tenant gets the four-row seed (cross_sell / up_sell / related /
 *     accessories) inline below; future tenants get them through
 *     {@see \App\Catalog\Application\BuiltInAssociationTypeSeeder}.
 *
 *   - `object_associations`: triple (source, target, type) with a
 *     position field for ordering. UNIQUE on the triple, CHECK
 *     constraint forbids self-loops, on-delete cascade follows the
 *     source/target so deleting an object cleans up its associations.
 *
 * The reverse direction is intentionally NOT mirrored — admins decide
 * whether (A→B cross_sell) implies (B→A cross_sell). Asymmetric
 * semantics like "this product replaces that one" stay possible.
 */
final class Version20260429050326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add association_types + object_associations tables (#35).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE association_types (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              label JSONB NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX association_types_tenant_idx ON association_types (tenant_id)');
        $this->addSql('CREATE INDEX association_types_tenant_position_idx ON association_types (tenant_id, position)');
        $this->addSql('CREATE UNIQUE INDEX association_types_tenant_code_uniq ON association_types (tenant_id, code)');
        $this->addSql('ALTER TABLE association_types ADD CONSTRAINT association_types_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE object_associations (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              source_object_id UUID NOT NULL,
              target_object_id UUID NOT NULL,
              type_id UUID NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT object_associations_no_self_loop_chk CHECK (source_object_id <> target_object_id)
            )
        SQL);
        $this->addSql('CREATE INDEX object_associations_tenant_idx ON object_associations (tenant_id)');
        $this->addSql('CREATE INDEX object_associations_source_idx ON object_associations (source_object_id)');
        $this->addSql('CREATE INDEX object_associations_target_idx ON object_associations (target_object_id)');
        $this->addSql('CREATE INDEX object_associations_type_idx ON object_associations (type_id)');
        $this->addSql('CREATE INDEX object_associations_source_type_idx ON object_associations (source_object_id, type_id)');
        $this->addSql('CREATE UNIQUE INDEX object_associations_triple_uniq ON object_associations (source_object_id, target_object_id, type_id)');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_source_fk FOREIGN KEY (source_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_target_fk FOREIGN KEY (target_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_type_fk FOREIGN KEY (type_id) REFERENCES association_types (id) ON DELETE RESTRICT NOT DEFERRABLE');

        // Seed the four default AssociationType rows per existing tenant.
        // BuiltInAssociationTypeSeeder is the runtime equivalent for future
        // tenants. Inline raw SQL keeps the migration self-contained — no
        // PHP service dependency at migration time.
        $this->seedDefaultTypes('cross_sell', 10, '{"pl":"Sprzedaż krzyżowa","en":"Cross-sell"}');
        $this->seedDefaultTypes('up_sell', 20, '{"pl":"Sprzedaż dodatkowa","en":"Up-sell"}');
        $this->seedDefaultTypes('related', 30, '{"pl":"Powiązane","en":"Related"}');
        $this->seedDefaultTypes('accessories', 40, '{"pl":"Akcesoria","en":"Accessories"}');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT object_associations_type_fk');
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT object_associations_target_fk');
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT object_associations_source_fk');
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT object_associations_tenant_fk');
        $this->addSql('DROP TABLE object_associations');

        $this->addSql('ALTER TABLE association_types DROP CONSTRAINT association_types_tenant_fk');
        $this->addSql('DROP TABLE association_types');
    }

    private function seedDefaultTypes(string $code, int $position, string $labelJson): void
    {
        $this->addSql(\sprintf(
            <<<'SQL'
                INSERT INTO association_types (id, tenant_id, code, label, position)
                SELECT gen_random_uuid(), t.id, '%s', '%s'::jsonb, %d
                FROM tenants t
                WHERE NOT EXISTS (
                    SELECT 1 FROM association_types a
                    WHERE a.tenant_id = t.id AND a.code = '%s'
                )
            SQL,
            $code,
            $labelJson,
            $position,
            $code,
        ));
    }
}
