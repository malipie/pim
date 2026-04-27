<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the `users` table — minimal Sprint-0 shape for ticket #4 (0.0.4):
 * email + password hash + JSON roles + tenant FK. Full RBAC model lands in
 * epic 0.2 (#24+); this is the surface LexikJWT authenticates against.
 */
final class Version20260427095515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table with tenant FK for JWT authentication (#4).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX users_tenant_idx ON users (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX users_email_uniq ON users (email)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT users_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT users_tenant_fk');
        $this->addSql('DROP TABLE users');
    }
}
