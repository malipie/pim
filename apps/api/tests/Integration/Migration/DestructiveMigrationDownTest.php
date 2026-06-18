<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AUD-041 (W2-6, finding G-03) — destructive migrations must signal
 * irreversibility LOUDLY rather than fake a schema-only rewind that silently
 * drops data.
 *
 * Six data-bearing migrations destroy data on `up()` and cannot reconstruct it
 * on `down()` (per-channel currency links, channel↔locale bindings, nulled
 * category roots, the `label.en` envelope, the in-place JSONB canon rewrite,
 * the per-row import-mode remap). Before AUD-041 most of their `down()` methods
 * recreated only the SCHEMA and returned "success" — so a
 * `migrations:migrate prev` reported a clean rollback while the data stayed
 * gone (a false round-trip). The fix makes each one call
 * {@see AbstractMigration::throwIrreversibleMigrationException()}.
 *
 * This test locks that contract on a fresh schema-built test DB:
 *  - every irreversible migration's `down()` throws {@see IrreversibleMigration}
 *    (red before the fix: the old `down()` queued ALTER/CREATE SQL and returned
 *    normally; green after: it throws);
 *  - a representative reversible migration's `down()` does NOT throw — so the
 *    suite is not trivially asserting "everything throws" (the negative control
 *    would fail if someone made ALL `down()` throw).
 *
 * Migrations are intentionally NOT autoloaded (see
 * config/packages/doctrine_migrations.yaml), so each file is `require_once`-d
 * and instantiated through the same `MigrationFactory` the migrator uses —
 * wiring the connection + logger — exactly as {@see \App\Tests\Integration\Catalog\CanonMigrationTest}
 * exercises the data SQL. `down()` only queues SQL via `addSql()` (or throws);
 * it never executes against the connection here, so no live rollback runs.
 */
final class DestructiveMigrationDownTest extends KernelTestCase
{
    /**
     * Data-bearing migrations whose `down()` must throw IrreversibleMigration.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function irreversibleMigrations(): iterable
    {
        yield '#1282 drop currencies + channel_currencies' => ['Version20260605100000'];
        yield 'CHC-01 nulled category roots' => ['Version20260606120000'];
        yield '#1316 channels.label -> name (loses en)' => ['Version20260607130000'];
        yield '#1318 drop channel_locales bindings' => ['Version20260607140000'];
        yield 'IMP2-1.2 JSONB canon (in-place rewrite)' => ['Version20260612210000'];
        yield 'IMP2-1.3 import-mode per-row remap' => ['Version20260612230000'];
    }

    #[Test]
    #[DataProvider('irreversibleMigrations')]
    public function destructiveDownThrowsIrreversible(string $version): void
    {
        $migration = $this->loadMigration($version);

        $this->expectException(IrreversibleMigration::class);

        $migration->down(new Schema());
    }

    /**
     * Negative control: a pure-structure additive migration round-trips cleanly,
     * so its `down()` must NOT throw IrreversibleMigration. If a future change
     * made every `down()` throw, this assertion fails — proving the positive
     * cases above test real behaviour, not a blanket rule.
     */
    #[Test]
    public function reversibleDownDoesNotThrow(): void
    {
        // IMP2-2.7 (#1483) — three nullable ADD COLUMN + their DROP COLUMN down.
        $migration = $this->loadMigration('Version20260615145641');

        $threw = false;
        try {
            $migration->down(new Schema());
        } catch (IrreversibleMigration) {
            $threw = true;
        }

        self::assertFalse($threw, 'a reversible additive migration must not declare itself irreversible');
    }

    private function loadMigration(string $version): AbstractMigration
    {
        self::bootKernel();

        // Migrations are not autoloaded (doctrine_migrations.yaml); define the
        // class from its file, then build it via the same factory the migrator
        // uses so the connection + logger are wired.
        require_once \dirname(__DIR__, 3).'/migrations/'.$version.'.php';

        // MigrationFactory::createVersion() returns an AbstractMigration with
        // the connection + logger wired — the same path the migrator uses.
        return self::getContainer()
            ->get('doctrine.migrations.dependency_factory')
            ->getMigrationFactory()
            ->createVersion('DoctrineMigrations\\'.$version);
    }
}
