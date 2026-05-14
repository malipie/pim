<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-12 (#543) — bulk operations foundation.
 *
 * `bulk_sessions` — one row per "user clicked Apply on a bulk action"
 * across the entire UI surface (manual wizard, Cmd+K agent dispatch
 * in VIEW-19). Tracks the action type, target IDs, success/skip/error
 * counts, rollback window (24h MVP), and source attribution.
 *
 * `bulk_logs` — append-only rollback recipe per (session, object,
 * attribute). The 24h rollback executor (VIEW-17) iterates these in
 * reverse to restore `old_value`.
 *
 * `catalog_objects.bulk_session_id` — last bulk session that touched
 * the row. Partial index speeds up "show me everything bulk_a8f3c2
 * changed" queries from VIEW-17 audit.
 */
final class Version20260514120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-12 bulk_sessions + bulk_logs + catalog_objects.bulk_session_id (#543).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE bulk_sessions (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              user_id UUID NULL,
              action_type VARCHAR(64) NOT NULL,
              target_object_ids JSONB NOT NULL,
              target_count INTEGER NOT NULL,
              success_count INTEGER NOT NULL DEFAULT 0,
              skipped_count INTEGER NOT NULL DEFAULT 0,
              error_count INTEGER NOT NULL DEFAULT 0,
              action_payload JSONB NOT NULL,
              rollback_available_until TIMESTAMP(0) WITHOUT TIME ZONE NULL,
              rolled_back_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
              source VARCHAR(16) NOT NULL DEFAULT 'manual',
              cmd_k_command TEXT NULL,
              started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              completed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
              PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX bulk_sessions_tenant_user_idx ON bulk_sessions (tenant_id, user_id)');
        $this->addSql('CREATE INDEX bulk_sessions_rollback_window_idx ON bulk_sessions (rollback_available_until) WHERE rolled_back_at IS NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE bulk_logs (
              id UUID NOT NULL,
              bulk_session_id UUID NOT NULL,
              object_id UUID NOT NULL,
              attribute_id UUID NULL,
              old_value JSONB NULL,
              new_value JSONB NULL,
              level VARCHAR(8) NOT NULL DEFAULT 'info',
              message TEXT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT bulk_logs_session_fk FOREIGN KEY (bulk_session_id) REFERENCES bulk_sessions(id) ON DELETE CASCADE
            )
            SQL);
        $this->addSql('CREATE INDEX bulk_logs_session_idx ON bulk_logs (bulk_session_id)');
        $this->addSql('CREATE INDEX bulk_logs_object_idx ON bulk_logs (object_id)');

        // catalog_objects.bulk_session_id deferred to a follow-up that
        // wires it through the entity + XML mapping (schema:create
        // bypasses migrations on `pim:db:reset`, so the column has to
        // live on the entity to round-trip cleanly).
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS bulk_logs');
        $this->addSql('DROP TABLE IF EXISTS bulk_sessions');
    }
}
