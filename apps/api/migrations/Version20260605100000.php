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
 * Destructive: existing channel↔currency links are lost. `down()` recreates
 * the schema and reseeds the three default currencies (without channel links).
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
        $this->addSql(<<<'SQL'
            CREATE TABLE currencies (
              id UUID NOT NULL,
              code VARCHAR(8) NOT NULL,
              symbol VARCHAR(8) NOT NULL,
              label VARCHAR(64) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX currencies_code_uniq ON currencies (code)');

        $this->addSql(<<<'SQL'
            CREATE TABLE channel_currencies (
              channel_id UUID NOT NULL,
              currency_id UUID NOT NULL,
              PRIMARY KEY (channel_id, currency_id)
            )
        SQL);
        $this->addSql('CREATE INDEX channel_currencies_channel_idx ON channel_currencies (channel_id)');
        $this->addSql('CREATE INDEX channel_currencies_currency_idx ON channel_currencies (currency_id)');
        $this->addSql('ALTER TABLE channel_currencies ADD CONSTRAINT channel_currencies_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE channel_currencies ADD CONSTRAINT channel_currencies_currency_fk FOREIGN KEY (currency_id) REFERENCES currencies (id) ON DELETE RESTRICT NOT DEFERRABLE');

        foreach ([
            ['PLN', 'zł', 'Polish złoty'],
            ['EUR', '€', 'Euro'],
            ['USD', '$', 'United States dollar'],
        ] as [$code, $symbol, $label]) {
            $this->addSql(\sprintf(
                "INSERT INTO currencies (id, code, symbol, label) SELECT gen_random_uuid(), '%s', '%s', '%s' WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = '%s')",
                $code,
                addslashes($symbol),
                addslashes($label),
                $code,
            ));
        }
    }
}
