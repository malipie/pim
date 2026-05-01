<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-02.6 (#296) — variant axes column on `objects`.
 *
 * `variant_axes JSONB` on the master row stores the axis definition
 * (`[{code, attribute_id, label, values}]`) the variants matrix
 * generator (UI-02.18 frontend) reads + writes via the new endpoint
 * `POST /api/products/{master_id}/generate-variants`.
 *
 * Variants themselves are existing rows — they reuse `objects.parent_id`
 * as the master self-reference (per the existing CatalogObject docblock:
 * *"parent_id is the self-FK used by: kind='product' for variants"*).
 * No new columns needed for the variant side; the master is the only
 * row that owns the axes definition.
 */
final class Version20260501160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UI-02.6 variant_axes JSONB on objects (#296).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE objects
              ADD COLUMN variant_axes JSONB NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objects DROP COLUMN variant_axes');
    }
}
