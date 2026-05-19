<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\Audit;

use App\Identity\Application\Audit\AuditLogScrubber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RBAC-P3-013 (#676) — AuditLogScrubber unit coverage of the sensitive-
 * field redaction.
 */
final class AuditLogScrubberTest extends TestCase
{
    #[Test]
    public function redactsSensitiveKeysAtTopLevel(): void
    {
        $scrubber = new AuditLogScrubber();

        $result = $scrubber->scrub([
            'email' => 'user@alpha.localhost',
            'password' => 'hunter2',
            'password_hash' => '$2y$13$...',
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        self::assertNotNull($result);
        self::assertSame('user@alpha.localhost', $result['email']);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $result['password']);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $result['password_hash']);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $result['mfa_secret']);
    }

    #[Test]
    public function redactsNestedSensitiveKeys(): void
    {
        $scrubber = new AuditLogScrubber();

        $result = $scrubber->scrub([
            'user' => [
                'name' => 'Marcin',
                'totp_secret' => 'JBSWY3DPEHPK3PXP',
            ],
            'integration' => [
                'shopify' => [
                    'access_token' => 'shpat_xxx',
                    'shop' => 'demo-store',
                ],
            ],
        ]);

        self::assertNotNull($result);
        $user = $result['user'];
        $integration = $result['integration'];
        self::assertIsArray($user);
        self::assertIsArray($integration);
        self::assertSame('Marcin', $user['name']);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $user['totp_secret']);
        $shopify = $integration['shopify'];
        self::assertIsArray($shopify);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $shopify['access_token']);
        self::assertSame('demo-store', $shopify['shop']);
    }

    #[Test]
    public function isCaseInsensitiveOnKeyNames(): void
    {
        $scrubber = new AuditLogScrubber();

        $result = $scrubber->scrub([
            'Password' => 'literal',
            'PASSWORD_HASH' => '$2y$...',
            'Access_Token' => 'shpat_xxx',
        ]);

        self::assertNotNull($result);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $result['Password']);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $result['PASSWORD_HASH']);
        self::assertSame(AuditLogScrubber::REDACTION_SENTINEL, $result['Access_Token']);
    }

    #[Test]
    public function nullPayloadReturnsNull(): void
    {
        self::assertNull(new AuditLogScrubber()->scrub(null));
    }

    #[Test]
    public function nonSensitiveDataPassesThrough(): void
    {
        $scrubber = new AuditLogScrubber();

        $result = $scrubber->scrub([
            'product_id' => '01931700-aaaa-bbbb-cccc-dddd00000001',
            'price' => 123.45,
            'in_stock' => true,
            'tags' => ['promo', 'new'],
        ]);

        self::assertSame([
            'product_id' => '01931700-aaaa-bbbb-cccc-dddd00000001',
            'price' => 123.45,
            'in_stock' => true,
            'tags' => ['promo', 'new'],
        ], $result);
    }
}
