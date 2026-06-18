<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1282 — drop currencies from channels.
 *
 * The `Currency` entity + `channel_currencies` M2M were used only inside the
 * Channel context (form/show/list/handlers). Product pricing is decoupled —
 * it stores ISO 4217 string codes in JSONB and validates them against a
 * per-attribute allow-list in PriceValidator, never against this table. With
 * the channel currency UI removed there are no remaining consumers, so the
 * junction and the global catalog table are dropped entirely.
 *
 * Destructive: existing channel↔currency links are lost. The reverse is
 * therefore irreversible — recreating the schema cannot recover the dropped
 * channel↔currency rows (AUD-041).
 */
final class Version20260605100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1282: drop channel_currencies junction + currencies table';
    }

    public function up(Schema $schema): void
    {
        // Junction first (FK to currencies is ON DELETE RESTRICT).
        $this->addSql('DROP TABLE IF EXISTS channel_currencies');
        $this->addSql('DROP TABLE IF EXISTS currencies');
    }

    public function down(Schema $schema): void
    {
        // AUD-041: `up()` dropped both `currencies` and the `channel_currencies`
        // junction. Recreating the schema + reseeding the three default
        // currencies could NOT restore the per-channel currency links (every
        // channel_currencies row is gone). A schema-only rewind that reports
        // success while the link data stays lost is a false round-trip; fail
        // loud and require a restore from the pre-dump instead.
        $this->throwIrreversibleMigrationException(
            'channel↔currency links (channel_currencies rows) were dropped and cannot be reconstructed from schema; '
            .'reseeding default currencies does not restore them. Take a pre-dump BEFORE this migration and restore '
            .'from it instead — see docs/runbook/destructive-migrations.md.',
        );
    }
}
