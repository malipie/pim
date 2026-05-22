<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Channel\Domain\Entity\Locale;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    #[Test]
    public function legacyConstructorRemainsSupported(): void
    {
        $locale = new Locale('pl_PL', 'Polski');

        self::assertSame('pl_PL', $locale->getCode());
        self::assertSame('Polski', $locale->getLabel());
        self::assertSame('', $locale->getLanguage());
        self::assertNull($locale->getRegion());
        self::assertSame([], $locale->getDisplayName());
        self::assertFalse($locale->isPopular());
    }

    #[Test]
    public function extendedConstructorAcceptsCatalogMetadata(): void
    {
        $locale = new Locale(
            'de_AT',
            'Niemiecki (Austria)',
            null,
            'de',
            'AT',
            ['pl' => 'Niemiecki (Austria)', 'en' => 'German (Austria)'],
            true,
        );

        self::assertSame('de', $locale->getLanguage());
        self::assertSame('AT', $locale->getRegion());
        self::assertSame('German (Austria)', $locale->getDisplayName()['en']);
        self::assertTrue($locale->isPopular());
    }

    #[Test]
    public function updateMetadataReplacesAllFields(): void
    {
        $locale = new Locale('pl_PL', 'Polski');

        $locale->updateMetadata('pl', 'PL', ['pl' => 'Polski (Polska)'], true);

        self::assertSame('pl', $locale->getLanguage());
        self::assertSame('PL', $locale->getRegion());
        self::assertSame(['pl' => 'Polski (Polska)'], $locale->getDisplayName());
        self::assertTrue($locale->isPopular());
    }
}
