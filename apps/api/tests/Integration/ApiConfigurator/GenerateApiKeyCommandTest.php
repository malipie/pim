<?php

declare(strict_types=1);

namespace App\Tests\Integration\ApiConfigurator;

use App\ApiConfigurator\Domain\Repository\ApiKeyRepositoryInterface;
use App\ApiConfigurator\Domain\Service\ApiKeyHasherInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class GenerateApiKeyCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function persistsKeyAndEchoesRawKeyOnce(): void
    {
        $tenant = $this->createTenant('demo');

        $tester = $this->commandTester();
        $exitCode = $tester->execute([
            '--tenant' => 'demo',
            '--name' => 'Storefront partner X',
            '--scopes' => 'storefront,sitemap',
            '--rate-limit' => '2000',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        $output = $tester->getDisplay();
        self::assertStringContainsString('API key generated', $output);
        self::assertMatchesRegularExpression('/pim_(?:test|live|dev)_[A-Za-z0-9]{32}/', $output, 'Raw key must be echoed in command output.');

        // Find the persisted ApiKey row.
        $repo = $this->apiKeyRepository();

        // The raw key is in the output — extract the prefix and assert
        // the persisted row matches it. Argon2id verify confirms the
        // raw key actually matches the stored hash.
        $matches = [];
        if (1 !== preg_match('/(pim_(?:test|live|dev)_[A-Za-z0-9]{32})/', $output, $matches)) {
            self::fail('Could not parse raw key from output.');
        }
        $rawKey = $matches[1];
        $prefix = substr($rawKey, 0, 12);

        $persisted = $repo->findByKeyPrefix($prefix);
        self::assertNotNull($persisted, 'ApiKey row must be persisted under the echoed prefix.');
        self::assertSame('Storefront partner X', $persisted->getName());
        self::assertSame(['storefront', 'sitemap'], $persisted->getScopes());
        self::assertSame(2000, $persisted->getRateLimitPerHour());
        self::assertSame($tenant->getId()->toRfc4122(), $persisted->getTenant()?->getId()->toRfc4122());

        $hasher = self::getContainer()->get(ApiKeyHasherInterface::class);
        self::assertInstanceOf(ApiKeyHasherInterface::class, $hasher);
        self::assertTrue($hasher->verify($rawKey, $persisted->getKeyHash()), 'Raw key must verify against the stored Argon2id hash.');

        // Defence-in-depth: the persisted row carries no plaintext.
        self::assertStringStartsWith('$argon2id$', $persisted->getKeyHash());
        self::assertStringNotContainsString($rawKey, $persisted->getKeyHash());
    }

    #[Test]
    public function failsWithUnknownTenant(): void
    {
        $tester = $this->commandTester();
        $exitCode = $tester->execute([
            '--tenant' => 'does-not-exist',
            '--name' => 'whatever',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    #[Test]
    public function rejectsMissingRequiredOptions(): void
    {
        $this->createTenant('demo');
        $tester = $this->commandTester();
        $exitCode = $tester->execute([
            '--tenant' => 'demo',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--name', $tester->getDisplay());
    }

    #[Test]
    public function setsExpiresAtWhenTtlGiven(): void
    {
        $this->createTenant('demo');
        $tester = $this->commandTester();
        $exitCode = $tester->execute([
            '--tenant' => 'demo',
            '--name' => 'short-lived',
            '--expires-in-days' => '30',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        $repo = $this->apiKeyRepository();

        $matches = [];
        if (1 !== preg_match('/(pim_(?:test|live|dev)_[A-Za-z0-9]{32})/', $tester->getDisplay(), $matches)) {
            self::fail('Could not parse raw key from output.');
        }
        $persisted = $repo->findByKeyPrefix(substr($matches[1], 0, 12));
        self::assertNotNull($persisted);
        self::assertNotNull($persisted->getExpiresAt());
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('pim:apikey:generate');

        return new CommandTester($command);
    }

    private function apiKeyRepository(): ApiKeyRepositoryInterface
    {
        $repo = self::getContainer()->get(ApiKeyRepositoryInterface::class);
        self::assertInstanceOf(ApiKeyRepositoryInterface::class, $repo);

        return $repo;
    }

    private function createTenant(string $code): Tenant
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $tenant = new Tenant($code, ucfirst($code).' Tenant');
        $em->persist($tenant);
        $em->flush();

        return $tenant;
    }
}
