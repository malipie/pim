<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1318 — drop the channel ↔ locale binding (`channel_locales`).
 *
 * A channel's locale subset was a declared config never enforced anywhere
 * (not value editing, completeness, nor validation) — the product/object
 * editor always used the tenant's locales. Per-channel locale targeting
 * belongs to the publication API (Faza 1), where the real "what ships to a
 * channel" decision lives. Removing it gives full per-(locale, channel)
 * editing freedom for every ObjectType. The rows are config bindings with
 * no business value, so the drop is data-safe.
 */
final class Version20260607140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1318: drop channel_locales (channel↔locale binding moved to publication API, Faza 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS channel_locales');
    }

    public function down(Schema $schema): void
    {
        // AUD-041: `up()` dropped `channel_locales` with all its rows.
        // Recreating an EMPTY table would report a successful rollback while
        // every channel↔locale binding stays lost — a false round-trip. The
        // bindings are config with no other source, so the reverse is lossy:
        // fail loud and require a restore from the pre-dump instead.
        $this->throwIrreversibleMigrationException(
            'channel↔locale bindings (channel_locales rows) were dropped; recreating an empty table does not '
            .'restore them. Take a pre-dump BEFORE this migration and restore from it instead — '
            .'see docs/runbook/destructive-migrations.md.',
        );
    }
}
