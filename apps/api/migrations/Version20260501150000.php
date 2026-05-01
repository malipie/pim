<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-02.3 (#293) — `bulk_edit_jobs` audit + status table.
 *
 * Tracks every POST `/api/products/bulk-edit` invocation so the
 * frontend can poll job status (`<BulkEditProgressModal>` UI-02.11) and
 * recover after a tab close. MVP runs the operation synchronously
 * inside the request — the row is the audit trail for a Faza 1 async
 * dispatch (Symfony Messenger queue `catalog`).
 *
 * Operation semantics + payload schema enforced in PHP, not as DB
 * CHECK, since the operation matrix is a moving target through MVP +
 * Faza 1 (publish, bulk delete, change family).
 */
final class Version20260501150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UI-02.3 bulk_edit_jobs audit + status table (#293).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE bulk_edit_jobs (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              user_id UUID NULL,
              operation VARCHAR(64) NOT NULL,
              payload JSONB NOT NULL DEFAULT '{}',
              total INT NOT NULL DEFAULT 0,
              processed INT NOT NULL DEFAULT 0,
              errors_count INT NOT NULL DEFAULT 0,
              first_errors JSONB NOT NULL DEFAULT '[]',
              status VARCHAR(16) NOT NULL DEFAULT 'pending',
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              completed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
              PRIMARY KEY (id)
            )
            SQL);

        $this->addSql('CREATE INDEX bulk_edit_jobs_tenant_status_idx ON bulk_edit_jobs (tenant_id, status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS bulk_edit_jobs_tenant_status_idx');
        $this->addSql('DROP TABLE IF EXISTS bulk_edit_jobs');
    }
}
