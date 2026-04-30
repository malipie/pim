<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.10 / ticket #93 — add `webhook_secret` to api_profiles.
 *
 * Per-profile HMAC secret used to sign outbound webhook bodies via
 * `X-Pim-Signature: sha256=<hex>`. The column is nullable because
 * existing rows from #90 must back-fill via the admin "Regenerate
 * secret" action — empty secret = webhooks disabled until rotation.
 */
final class Version20260430140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add webhook_secret to api_profiles for HMAC delivery signing (#93).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_profiles ADD COLUMN webhook_secret VARCHAR(128) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_profiles DROP COLUMN webhook_secret');
    }
}
