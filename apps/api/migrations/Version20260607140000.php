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
        $this->addSql(<<<'SQL'
            CREATE TABLE channel_locales (
              channel_id UUID NOT NULL,
              locale_id UUID NOT NULL,
              PRIMARY KEY (channel_id, locale_id)
            )
        SQL);
        $this->addSql('CREATE INDEX channel_locales_channel_idx ON channel_locales (channel_id)');
        $this->addSql('CREATE INDEX channel_locales_locale_idx ON channel_locales (locale_id)');
        $this->addSql('ALTER TABLE channel_locales ADD CONSTRAINT channel_locales_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_locales ADD CONSTRAINT channel_locales_locale_fk FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }
}
