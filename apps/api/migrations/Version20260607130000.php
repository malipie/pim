<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1316 — replace the bilingual channel `label` (JSONB `{pl, en}`) with a
 * single scalar `name` (VARCHAR).
 *
 * The channel label was only ever an internal admin display name; nothing
 * publishes it to a destination, so a multi-language envelope was needless
 * over-engineering. The expand step backfills `name` from the existing
 * `label` (pl → en → code) so live channels keep their display names, then
 * the `label` column is dropped.
 */
final class Version20260607130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1316: channels.label (jsonb) -> channels.name (varchar), backfilled from label.pl/en/code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channels ADD name VARCHAR(255) DEFAULT NULL');
        // tenant-safe: one-time data backfill across every channel row at
        // migration time; the name is derived from each row's own label/code.
        $this->addSql("UPDATE channels SET name = COALESCE(NULLIF(label->>'pl', ''), NULLIF(label->>'en', ''), code)");
        $this->addSql('ALTER TABLE channels ALTER COLUMN name SET NOT NULL');
        $this->addSql('ALTER TABLE channels DROP label');
    }

    public function down(Schema $schema): void
    {
        // AUD-041: the reverse is LOSSY, not reversible. `up()` collapsed the
        // bilingual `{pl, en}` (or any other) envelope into a single scalar
        // `name`. Rebuilding `label` as `{"pl": name}` would silently discard
        // every non-`pl` key (`en`, …) while reporting success — a false
        // round-trip. Fail loud instead and force a restore from the pre-dump.
        $this->throwIrreversibleMigrationException(
            'channels.label envelope (en + any non-pl key) was discarded when collapsing to channels.name; '
            .'a rebuilt {"pl": name} would silently lose it. Take a pre-dump BEFORE this migration and restore '
            .'from it instead — see docs/runbook/destructive-migrations.md.',
        );
    }
}
