<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * `pim:security:unblock-ip` operator escape hatch (#97 / 0.11.2).
 *
 * Asserts the command resets both auth limiters keyed on the supplied
 * IP — a previously locked-out address can immediately log in again.
 */
final class UnblockIpCommandTest extends KernelTestCase
{
    #[Test]
    public function unblockResetsAuthLimitersForGivenIp(): void
    {
        self::bootKernel();
        // Use the test container (`getContainer()`) — the production
        // service is private, but Symfony's test container exposes
        // private services for inspection. The CLI under test uses
        // injected factories so production wiring stays clean.
        $login = self::getContainer()->get('limiter.auth_login');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $login);
        $refresh = self::getContainer()->get('limiter.auth_refresh');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $refresh);

        // Exhaust both buckets for 1.2.3.4 — sixth login + thirty-first
        // refresh would 429 in the live listener.
        for ($i = 0; $i < 5; ++$i) {
            $login->create('1.2.3.4')->consume();
        }
        for ($i = 0; $i < 30; ++$i) {
            $refresh->create('1.2.3.4')->consume();
        }
        self::assertFalse($login->create('1.2.3.4')->consume()->isAccepted());
        self::assertFalse($refresh->create('1.2.3.4')->consume()->isAccepted());

        $tester = $this->commandTester();
        $exit = $tester->execute(['ip' => '1.2.3.4']);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());
        // After reset both buckets accept again.
        self::assertTrue($login->create('1.2.3.4')->consume()->isAccepted());
        self::assertTrue($refresh->create('1.2.3.4')->consume()->isAccepted());
    }

    #[Test]
    public function rejectsInvalidIp(): void
    {
        $tester = $this->commandTester();
        $exit = $tester->execute(['ip' => 'not-an-ip']);

        self::assertSame(Command::INVALID, $exit);
        self::assertStringContainsString('not a valid IP', $tester->getDisplay());
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('pim:security:unblock-ip');

        return new CommandTester($command);
    }
}
