<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\ResetDatabase;

final class TenantAuditCommandTest extends KernelTestCase
{
    use ResetDatabase;

    #[Test]
    public function reportsCleanStateAfterAllMigrations(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());
        $output = $tester->getDisplay();
        // Every domain table the audit knows about today should land green —
        // regression guard for future schema work. After #33 (ADR-009 data
        // migration) the legacy `products` table is gone; the inventory
        // covers `objects` + `object_values` instead.
        self::assertStringContainsString('All domain tables carry tenant_id.', $output);
        foreach (['objects', 'object_values', 'refresh_tokens', 'users', 'roles'] as $table) {
            self::assertStringContainsString($table, $output, \sprintf('Audit must list %s.', $table));
        }
    }

    #[Test]
    public function flagsMissingTenantIdWhenADomainTableLacksIt(): void
    {
        $kernel = self::bootKernel();
        $connection = $kernel->getContainer()->get('doctrine')->getConnection();
        \assert($connection instanceof \Doctrine\DBAL\Connection);
        // Drop a domain column to simulate a forgotten migration. Any non-
        // infra table works; `objects` is the canonical post-#33 example
        // (the legacy `products` table no longer exists).
        $connection->executeStatement('ALTER TABLE objects DROP COLUMN tenant_id CASCADE');

        try {
            $tester = $this->commandTester();
            $exitCode = $tester->execute([]);

            self::assertSame(Command::FAILURE, $exitCode);
            self::assertStringContainsString('MISSING', $tester->getDisplay());
            self::assertStringContainsString('Audit found', $tester->getDisplay());
        } finally {
            // Restore the column so subsequent tests in the same kernel boot
            // do not see the broken schema. ResetDatabase between tests
            // would also clean up, but explicit is friendlier.
            $connection->executeStatement('ALTER TABLE objects ADD COLUMN tenant_id UUID');
        }
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('pim:tenant:audit');

        return new CommandTester($command);
    }
}
