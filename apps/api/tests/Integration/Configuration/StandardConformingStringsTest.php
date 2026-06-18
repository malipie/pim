<?php

declare(strict_types=1);

namespace App\Tests\Integration\Configuration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AUD-031 / W2-3 (C-2) — regression guard for the FilterDSL escaping
 * security premise.
 *
 * {@see \App\Catalog\Application\Filter\FilterDslResolver} compiles the
 * product/export filter DSL to parameter-free SQL, escaping string literals
 * by doubling single quotes (`'` → `''`). That escaping is ONLY safe while
 * Postgres runs with `standard_conforming_strings = on`: with the setting
 * `off`, a backslash inside a literal escapes the closing quote (`\'`) and
 * the doubled-quote escaping leaks, re-opening the SQL-injection vector the
 * audit confirmed is otherwise contained.
 *
 * The setting defaults `on` since Postgres 9.1, but the audit demands a
 * *guarantee*, not an assumption. {@see \App\Shared\Infrastructure\Doctrine\Middleware\StandardConformingStringsMiddleware}
 * forces `SET standard_conforming_strings = on` on every physical connection
 * and fails loud if the server somehow refuses. This test pins that the live
 * runtime connection reports `on`, so flipping the server default (or
 * regressing the middleware) turns the suite red.
 */
final class StandardConformingStringsTest extends KernelTestCase
{
    #[Test]
    public function runtimeConnectionEnforcesStandardConformingStrings(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        $value = $connection->fetchOne('SHOW standard_conforming_strings');

        self::assertSame(
            'on',
            $value,
            'FilterDSL literal escaping (single-quote doubling) is only injection-safe while '
            .'standard_conforming_strings is on; the connection-init middleware must guarantee it.',
        );
    }

    #[Test]
    public function escapedLiteralIsInterpretedAsASingleStringNotInjection(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        // The exact escaping FilterDslResolver::literal() emits for the
        // adversarial value `x' OR '1'='1`: doubled single quotes. Under
        // standard_conforming_strings=on Postgres MUST read this as one
        // string literal equal to the original input, never as a closed
        // string followed by an OR predicate.
        $roundTripped = $connection->fetchOne("SELECT 'x'' OR ''1''=''1'");

        self::assertSame("x' OR '1'='1", $roundTripped);
    }

    private function connection(): Connection
    {
        // The container PHPStan extension types this service id as Connection.
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
