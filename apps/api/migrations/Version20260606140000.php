<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CHC-04 (#1288) — schema-drift tracking columns on `objects`.
 *
 * `schema_snapshot` captures the effective attribute-group ids a product had
 * when first filled; `schema_drift` is flagged by the async
 * CheckSchemaDriftHandler when a category move changes that effective set, and
 * cleared when the operator acknowledges. Both default to "no snapshot / no
 * drift" so existing rows are inert until first touched.
 */
final class Version20260606140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CHC-04: add objects.schema_snapshot (jsonb) + objects.schema_drift (bool)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objects ADD COLUMN schema_snapshot JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE objects ADD COLUMN schema_drift BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objects DROP COLUMN schema_snapshot');
        $this->addSql('ALTER TABLE objects DROP COLUMN schema_drift');
    }
}
