<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Structural imports (attribute / attribute-group definitions) reuse the
 * import_sessions table but carry no target ObjectType. Make
 * target_object_type_id nullable and add a structural_kind discriminator
 * (`attributes` | `attribute_groups`, null = the CatalogObject pipeline) so the
 * worker can route a session to the structural handler.
 */
final class Version20260624140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make import_sessions.target_object_type_id nullable and add structural_kind.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions ALTER COLUMN target_object_type_id DROP NOT NULL');
        $this->addSql('ALTER TABLE import_sessions ADD structural_kind VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions DROP structural_kind');
        $this->addSql('ALTER TABLE import_sessions ALTER COLUMN target_object_type_id SET NOT NULL');
    }
}
