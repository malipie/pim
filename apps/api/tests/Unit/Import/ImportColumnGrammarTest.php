<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Channel\Contracts\ScopeEnumeratorInterface;
use App\Import\Application\Service\ImportColumnGrammar;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-1.6 (#1469, ADR-0019) — the column grammar is pure parsing logic;
 * this test pins every branch of `code` / `code.locale` / `code.channel`
 * / `code.locale.channel`, the locale-wins collision rule, and the
 * unknown-suffix column error (never a silent locale).
 */
final class ImportColumnGrammarTest extends TestCase
{
    private const string SHOPIFY_ID = '0190aaaa-bbbb-7ccc-8ddd-000000000001';

    #[Test]
    public function bareCodeHasNoLocaleOrChannel(): void
    {
        $parsed = $this->grammar(['pl', 'en'], ['shopify' => self::SHOPIFY_ID])->parse('price', $this->tenant());

        self::assertSame('price', $parsed->base);
        self::assertNull($parsed->locale);
        self::assertNull($parsed->channelCode);
        self::assertNull($parsed->channelId);
        self::assertNull($parsed->unknownSuffix);
        self::assertFalse($parsed->localeChannelCollision);
    }

    #[Test]
    public function localeSuffixResolvesToLocale(): void
    {
        $parsed = $this->grammar(['pl', 'en'], [])->parse('name.pl', $this->tenant());

        self::assertSame('name', $parsed->base);
        self::assertSame('pl', $parsed->locale);
        self::assertNull($parsed->channelCode);
        self::assertNull($parsed->channelId);
        self::assertNull($parsed->unknownSuffix);
    }

    #[Test]
    public function channelSuffixResolvesToChannelIdNotLocale(): void
    {
        $parsed = $this->grammar(['pl'], ['shopify' => self::SHOPIFY_ID])->parse('price.shopify', $this->tenant());

        self::assertSame('price', $parsed->base);
        self::assertNull($parsed->locale, 'channel suffix must not become a bogus locale row');
        self::assertSame('shopify', $parsed->channelCode);
        self::assertInstanceOf(Uuid::class, $parsed->channelId);
        self::assertSame(self::SHOPIFY_ID, $parsed->channelId->toRfc4122());
    }

    #[Test]
    public function combinedLocaleChannelResolvesBoth(): void
    {
        $parsed = $this->grammar(['pl', 'en'], ['shopify' => self::SHOPIFY_ID])->parse('description.pl.shopify', $this->tenant());

        self::assertSame('description', $parsed->base);
        self::assertSame('pl', $parsed->locale);
        self::assertSame('shopify', $parsed->channelCode);
        self::assertInstanceOf(Uuid::class, $parsed->channelId);
        self::assertSame(self::SHOPIFY_ID, $parsed->channelId->toRfc4122());
        self::assertNull($parsed->unknownSuffix);
    }

    #[Test]
    public function unknownSingleSuffixIsColumnErrorNotSilentLocale(): void
    {
        $parsed = $this->grammar(['pl', 'en'], ['shopify' => self::SHOPIFY_ID])->parse('name.xx', $this->tenant());

        self::assertSame('name', $parsed->base);
        self::assertNull($parsed->locale);
        self::assertNull($parsed->channelCode);
        self::assertSame('xx', $parsed->unknownSuffix);
    }

    #[Test]
    public function localeWinsOnCollisionWithChannelOfSameCode(): void
    {
        // ADR-0019 precedence: a channel named like an active locale ('en')
        // resolves to the LOCALE, flagged so dry-run can warn.
        $parsed = $this->grammar(['en'], ['en' => self::SHOPIFY_ID])->parse('name.en', $this->tenant());

        self::assertSame('en', $parsed->locale);
        self::assertNull($parsed->channelCode);
        self::assertNull($parsed->channelId);
        self::assertTrue($parsed->localeChannelCollision);
    }

    #[Test]
    public function combinedWithUnknownChannelIsColumnError(): void
    {
        $parsed = $this->grammar(['pl'], ['shopify' => self::SHOPIFY_ID])->parse('description.pl.allegro', $this->tenant());

        self::assertSame('description', $parsed->base);
        self::assertNull($parsed->locale);
        self::assertNull($parsed->channelId);
        self::assertSame('pl.allegro', $parsed->unknownSuffix);
    }

    #[Test]
    public function combinedWithUnknownLocaleIsColumnError(): void
    {
        $parsed = $this->grammar(['pl'], ['shopify' => self::SHOPIFY_ID])->parse('description.zz.shopify', $this->tenant());

        self::assertSame('zz.shopify', $parsed->unknownSuffix);
    }

    #[Test]
    public function moreThanTwoModifiersIsColumnError(): void
    {
        $parsed = $this->grammar(['pl'], ['shopify' => self::SHOPIFY_ID])->parse('a.pl.shopify.extra', $this->tenant());

        self::assertSame('a', $parsed->base);
        self::assertSame('pl.shopify.extra', $parsed->unknownSuffix);
    }

    #[Test]
    public function baseOfExtractsTheAttributeCodeSegment(): void
    {
        self::assertSame('description', ImportColumnGrammar::baseOf('description.pl.shopify'));
        self::assertSame('name', ImportColumnGrammar::baseOf('name.pl'));
        self::assertSame('price', ImportColumnGrammar::baseOf('price'));
    }

    /**
     * @param list<string>          $localeShortCodes
     * @param array<string, string> $channelIdsByCode code => rfc4122
     */
    private function grammar(array $localeShortCodes, array $channelIdsByCode): ImportColumnGrammar
    {
        $scopes = $this->createStub(ScopeEnumeratorInterface::class);
        $scopes->method('localeShortCodes')->willReturn($localeShortCodes);
        $scopes->method('channelIdsByCode')->willReturn($channelIdsByCode);

        return new ImportColumnGrammar($scopes);
    }

    private function tenant(): Tenant
    {
        return new Tenant('demo', 'Demo');
    }
}
