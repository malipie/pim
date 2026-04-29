<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add `processed_messages` table for the IdempotencyMiddleware (RF-20).
 *
 * Holds Messenger envelope ids that have already been handled. Subscribers
 * stamped with the middleware short-circuit when they see a duplicate id —
 * makes safe-by-default at-least-once delivery (Doctrine async transport
 * retries) idempotent at the handler boundary.
 *
 * Stays empty until async transports come online (epic 0.5+); the table is
 * provisioned ahead of time so the first async handler does not need a
 * schema migration too.
 */
final class Version20260429170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add processed_messages table for Messenger idempotency middleware (#170).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE processed_messages (
                message_id UUID PRIMARY KEY,
                handler_class VARCHAR(255) NOT NULL,
                processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX processed_messages_handler_idx ON processed_messages (handler_class)');
        $this->addSql('CREATE INDEX processed_messages_processed_at_idx ON processed_messages (processed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_messages');
    }
}
