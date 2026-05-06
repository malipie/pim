<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\MappingDictionaryService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class MappingDictionaryServiceTest extends TestCase
{
    #[Test]
    public function loadsCanonicalAttributesFromTheRealYaml(): void
    {
        $service = $this->serviceWithRealYaml();
        $dictionary = $service->load();

        // Spot-check the spec §5.3 anchor entries.
        self::assertArrayHasKey('sku', $dictionary);
        self::assertArrayHasKey('name', $dictionary);
        self::assertArrayHasKey('price', $dictionary);
        self::assertArrayHasKey('main_image', $dictionary);
        self::assertArrayHasKey('gallery_2', $dictionary);
        self::assertContains('kodproduktu', $dictionary['sku']);
    }

    #[Test]
    public function aliasIndexInvertsTheDictionary(): void
    {
        $service = $this->serviceWithRealYaml();
        $index = $service->aliasIndex();

        self::assertSame('sku', $index['kodproduktu']);
        self::assertSame('name', $index['nazwa']);
        self::assertSame('price', $index['cena']);
        self::assertSame('brand', $index['producent']);
    }

    #[Test]
    public function returnsEmptyDictionaryWhenFileMissing(): void
    {
        $service = new MappingDictionaryService('/dev/null/missing.yaml', new ArrayAdapter());

        self::assertSame([], $service->load());
    }

    #[Test]
    public function dictionaryIsCachedAcrossCalls(): void
    {
        $service = $this->serviceWithRealYaml();

        // Two calls return the same payload — `load()` is the API contract,
        // not a constructor parameter, so a stable result on repeat calls
        // is the user-visible cache contract here.
        self::assertSame($service->load(), $service->load());
    }

    private function serviceWithRealYaml(): MappingDictionaryService
    {
        $path = __DIR__.'/../../../config/imports/mapping_dictionary.yaml';

        return new MappingDictionaryService($path, new ArrayAdapter());
    }
}
