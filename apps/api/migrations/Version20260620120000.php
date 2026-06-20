<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AUD-078 (W3-1) — drop the orphaned `object_associations_audit` /
 * `association_types_audit` EntityAuditBundle shadow tables.
 *
 * ADR-014 (Version20260524110000) dropped the dormant `object_associations`
 * and `association_types` base tables when `object_relations` replaced them.
 * The matching DamianBadura/EntityAuditBundle audit tables (created in
 * Version20260430092112) were left behind: their owning entities no longer
 * exist, no audit trigger or revision flow writes into them, and they hold
 * zero rows. They are pure schema sludge — a confusing leftover for anyone
 * reading `\dt`.
 *
 * This migration drops both. Verified before writing: both tables exist,
 * each has 0 rows, and no trigger / trigger-function references them.
 *
 * Down: irreversible. The tables shadow entities that no longer exist, so
 * recreating empty audit shells would only resurrect the same orphan state
 * ADR-014 set out to remove — there is nothing to restore.
 */
final class Version20260620120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUD-078: drop orphaned object_associations_audit / association_types_audit shadow tables (ADR-014 leftovers).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS object_associations_audit');
        $this->addSql('DROP TABLE IF EXISTS association_types_audit');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Audit shadow tables for entities removed in ADR-014 are not restored — recreating empty shells would only reintroduce the orphan state.',
        );
    }
}
