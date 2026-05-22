<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Locales feature (#874, LOC-06) — revert `channel_locales.tenant_id`.
 *
 * `Version20260521090000` added `tenant_id` to the `channel_locales` PK
 * with the goal of letting Postgres RLS scope the junction directly. In
 * practice the CI test pipeline rebuilds the schema from Doctrine ORM
 * metadata (`Foundry::ResetDatabase`), where the M2M mapping does not
 * carry the extra column — every test against `/api/channel-locales`
 * fails with `column cl.tenant_id does not exist`.
 *
 * Rather than refactor the M2M into an explicit `ChannelLocale` aggregate
 * just to keep the column, we drop it: tenant isolation flows through
 * `channels.tenant_id` (FK chain), and the read/write SQL in
 * `ChannelLocaleMatrixController` now joins channels for the WHERE clause
 * and omits the column on INSERT. RLS for `channel_locales` can be
 * reintroduced in the RBAC Phase 2 #654 ticket via a Postgres policy that
 * dereferences through the FK.
 */
final class Version20260522100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Locales (#874): drop channel_locales.tenant_id — schema parity with Foundry ResetDatabase.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT IF EXISTS channel_locales_tenant_fk');
        $this->addSql('DROP INDEX IF EXISTS channel_locales_tenant_idx');
        $this->addSql('ALTER TABLE channel_locales DROP CONSTRAINT IF EXISTS channel_locales_pkey');
        $this->addSql('ALTER TABLE channel_locales DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE channel_locales
            ADD CONSTRAINT channel_locales_pkey PRIMARY KEY (channel_id, locale_id)');
    }

    public function down(Schema $schema): void
    {
        // Reverse path lives in Version20260521090000.
    }
}
