<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1312 — drop the dead `channel_object_type_mappings` table.
 *
 * The per-channel attribute→target-field mapping was scaffolded in epic 0.3
 * but never wired to a row generator (the table was always empty) and, after
 * #1308, nothing reads it. Attribute mapping returns with the first real API
 * export integration (Faza 1). The table carries no data of value.
 */
final class Version20260607120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1312: drop dead channel_object_type_mappings (attribute→field mapping moved to API integration config, Faza 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS channel_object_type_mappings');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE channel_object_type_mappings (
              id UUID NOT NULL,
              channel_id UUID NOT NULL,
              object_type_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              target_field VARCHAR(255) NOT NULL,
              is_published BOOLEAN DEFAULT true NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX channel_object_type_mappings_channel_idx ON channel_object_type_mappings (channel_id)');
        $this->addSql('CREATE INDEX channel_object_type_mappings_object_type_idx ON channel_object_type_mappings (object_type_id)');
        $this->addSql('CREATE INDEX channel_object_type_mappings_attribute_idx ON channel_object_type_mappings (attribute_id)');
        $this->addSql('CREATE UNIQUE INDEX channel_object_type_mappings_triple_uniq ON channel_object_type_mappings (channel_id, object_type_id, attribute_id)');
        $this->addSql('ALTER TABLE channel_object_type_mappings ADD CONSTRAINT channel_object_type_mappings_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_object_type_mappings ADD CONSTRAINT channel_object_type_mappings_object_type_fk FOREIGN KEY (object_type_id) REFERENCES object_types (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_object_type_mappings ADD CONSTRAINT channel_object_type_mappings_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE NOT DEFERRABLE');
    }
}
