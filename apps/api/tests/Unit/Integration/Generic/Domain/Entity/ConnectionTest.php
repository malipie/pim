<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\ConnectionStatus;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionTest extends TestCase
{
    #[Test]
    public function newConnectionDefaultsToDraftWithoutCredentials(): void
    {
        $c = new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');

        self::assertSame('idosell', $c->getCode());
        self::assertSame('IdoSell PL', $c->getName());
        self::assertSame('https://api.idosell.com', $c->getBaseUrl());
        self::assertSame(AuthType::None, $c->getAuthType());
        self::assertSame(ConnectionStatus::Draft, $c->getStatus());
        self::assertSame([], $c->getDefaultHeaders());
        self::assertNull($c->getCredentialsCiphertext());
        self::assertNull($c->getCredentialsKeyVersion());
        self::assertNull($c->getRateLimitHint());
        self::assertNull($c->getLastHealthCheckAt());
        self::assertNull($c->getTenant());
    }

    #[Test]
    public function constructorAcceptsAuthType(): void
    {
        $c = new Connection('shopify', 'Shopify', 'https://x.myshopify.com', AuthType::Bearer);

        self::assertSame(AuthType::Bearer, $c->getAuthType());
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $c = $this->connection();
        $c->setName('Renamed');
        $c->setBaseUrl('https://new.example.com');
        $c->setAuthType(AuthType::ApiKey);
        $c->setStatus(ConnectionStatus::Active);
        $c->setDefaultHeaders(['X-Trace' => '1']);
        $c->setRateLimitHint(120);

        self::assertSame('Renamed', $c->getName());
        self::assertSame('https://new.example.com', $c->getBaseUrl());
        self::assertSame(AuthType::ApiKey, $c->getAuthType());
        self::assertSame(ConnectionStatus::Active, $c->getStatus());
        self::assertSame(['X-Trace' => '1'], $c->getDefaultHeaders());
        self::assertSame(120, $c->getRateLimitHint());
    }

    #[Test]
    public function setCredentialsStoresAndClearsTheBlob(): void
    {
        $c = $this->connection();

        $c->setCredentials('base64blob==', 3);
        self::assertSame('base64blob==', $c->getCredentialsCiphertext());
        self::assertSame(3, $c->getCredentialsKeyVersion());

        $c->setCredentials(null, null);
        self::assertNull($c->getCredentialsCiphertext());
        self::assertNull($c->getCredentialsKeyVersion());
    }

    #[Test]
    public function recordHealthCheckStampsTimestampAndStatus(): void
    {
        $c = $this->connection();
        $at = new DateTimeImmutable('2026-06-27T10:00:00+00:00');

        $c->recordHealthCheck($at, ConnectionStatus::Error);

        self::assertEquals($at, $c->getLastHealthCheckAt());
        self::assertSame(ConnectionStatus::Error, $c->getStatus());
    }

    #[Test]
    public function assignTenantIsWriteOnce(): void
    {
        $c = $this->connection();
        $c->assignTenant(new Tenant('alpha', 'Alpha'));
        self::assertNotNull($c->getTenant());

        $this->expectException(LogicException::class);
        $c->assignTenant(new Tenant('beta', 'Beta'));
    }

    #[Test]
    public function onlyNoneAuthTypeIsCredentialFree(): void
    {
        self::assertFalse(AuthType::None->requiresCredentials());
        self::assertTrue(AuthType::ApiKey->requiresCredentials());
        self::assertTrue(AuthType::Bearer->requiresCredentials());
        self::assertTrue(AuthType::Basic->requiresCredentials());
        self::assertTrue(AuthType::Oauth2Token->requiresCredentials());
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');
    }
}
