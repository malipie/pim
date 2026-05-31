<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\AutoMapper;
use App\Import\Application\Service\MappingDictionaryProvider;
use App\Import\Application\Service\MappingDictionaryService;
use App\Import\Domain\Enum\MappingConfidence;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class AutoMapperTest extends TestCase
{
    #[Test]
    public function festoFixtureHits12OutOf15ColumnsExactMatches(): void
    {
        $mapper = $this->mapperWithRealDictionary();
        $available = $this->festoAvailableAttributeCodes();

        $headers = [
            'Kod produktu', 'Nazwa', 'Cena netto', 'Producent', 'Kategoria',
            'EAN', 'Description', 'image_1', 'image_2', 'image_3',
            'IP_class', 'Numer Festo', 'Srednica zewn.', 'Stara cena', 'Notatki wewn.',
        ];

        $suggestions = $mapper->map($available, $headers, []);
        self::assertCount(15, $suggestions);

        $auto = array_filter($suggestions, static fn ($s) => MappingConfidence::Auto === $s->confidence);
        $manual = array_filter($suggestions, static fn ($s) => MappingConfidence::Manual === $s->confidence);

        self::assertCount(12, $auto, 'Spec §5.3: 12 auto-matched columns on the festo fixture.');
        self::assertCount(3, $manual);

        $byHeader = [];
        foreach ($suggestions as $suggestion) {
            $byHeader[$suggestion->columnHeader] = $suggestion->suggestedAttributeCode;
        }
        self::assertSame('sku', $byHeader['Kod produktu']);
        self::assertSame('name', $byHeader['Nazwa']);
        self::assertSame('price', $byHeader['Cena netto']);
        self::assertSame('brand', $byHeader['Producent']);
        self::assertSame('__category__', $byHeader['Kategoria'], '"Kategoria" resolves to the reserved category-assignment target, not a regular Attribute.');
        self::assertSame('ean', $byHeader['EAN']);
        self::assertSame('main_image', $byHeader['image_1']);
        self::assertSame('gallery_3', $byHeader['image_3']);
        self::assertSame('ip_class', $byHeader['IP_class']);
        self::assertSame('diameter', $byHeader['Srednica zewn.']);
        self::assertNull($byHeader['Numer Festo']);
        self::assertNull($byHeader['Stara cena']);
    }

    #[Test]
    public function fuzzyMatchesTypoWithLevenshteinUnderTwo(): void
    {
        $mapper = $this->mapperWithDictionary([
            'product' => ['aliases' => ['name', 'nazwa', 'tytul']],
        ]);

        $suggestions = $mapper->map(['product'], ['nawa'], []);

        self::assertSame(MappingConfidence::Fuzzy, $suggestions[0]->confidence);
        self::assertSame('product', $suggestions[0]->suggestedAttributeCode);
    }

    #[Test]
    public function manualWhenNoMatchAnywhere(): void
    {
        $mapper = $this->mapperWithDictionary([
            'sku' => ['aliases' => ['sku', 'kod']],
        ]);

        $suggestions = $mapper->map(['sku'], ['Numer wewnetrzny'], []);

        self::assertSame(MappingConfidence::Manual, $suggestions[0]->confidence);
        self::assertNull($suggestions[0]->suggestedAttributeCode);
    }

    #[Test]
    public function emptyHeaderBecomesSkip(): void
    {
        $mapper = $this->mapperWithDictionary([]);

        $suggestions = $mapper->map([], ['', '   '], []);

        self::assertSame(MappingConfidence::Skip, $suggestions[0]->confidence);
        self::assertSame(MappingConfidence::Skip, $suggestions[1]->confidence);
    }

    #[Test]
    public function suggestsAreFilteredAgainstAvailableAttributes(): void
    {
        // dictionary maps "kod" → "sku" but the ObjectType does not have "sku".
        $mapper = $this->mapperWithDictionary([
            'sku' => ['aliases' => ['kod']],
        ]);

        $suggestions = $mapper->map(['name'], ['kod'], []);

        // Auto would have suggested "sku" but it is not on the available
        // list, so the matcher falls back to manual.
        self::assertSame(MappingConfidence::Manual, $suggestions[0]->confidence);
    }

    #[Test]
    public function localisedHeaderMatchesItsAttributeBase(): void
    {
        // Re-importing an export: `name.pl` / `description.en` must auto-map
        // to their attribute (the locale is re-derived from the header
        // later), not fall through to manual (#1130).
        $mapper = $this->mapperWithDictionary([
            'name' => ['aliases' => ['name', 'nazwa']],
            'description' => ['aliases' => ['description', 'opis']],
        ]);

        $suggestions = $mapper->map(
            ['name', 'description'],
            ['name.pl', 'description.en'],
            [],
        );

        self::assertSame('name', $suggestions[0]->suggestedAttributeCode);
        self::assertSame(MappingConfidence::Auto, $suggestions[0]->confidence);
        self::assertSame('description', $suggestions[1]->suggestedAttributeCode);
        self::assertSame(MappingConfidence::Auto, $suggestions[1]->confidence);
    }

    #[Test]
    public function systemColumnsAreSuggestedAsSkip(): void
    {
        $mapper = $this->mapperWithDictionary([
            'sku' => ['aliases' => ['sku']],
        ]);

        $suggestions = $mapper->map(
            ['sku'],
            ['sku', 'created_at', 'updated_at', 'completeness_pct', 'status', 'enabled', 'parent_sku'],
            [],
        );

        self::assertSame(MappingConfidence::Auto, $suggestions[0]->confidence, 'sku still auto-maps.');
        foreach (\array_slice($suggestions, 1) as $suggestion) {
            self::assertSame(
                MappingConfidence::Skip,
                $suggestion->confidence,
                \sprintf('System column "%s" should be Skip.', $suggestion->columnHeader),
            );
            self::assertNull($suggestion->suggestedAttributeCode);
        }
    }

    #[Test]
    public function diacriticsAreNormalisedThroughTheStripper(): void
    {
        $mapper = $this->mapperWithRealDictionary();

        $suggestions = $mapper->map(['diameter'], ['Średnica zewn.'], []);

        self::assertSame('diameter', $suggestions[0]->suggestedAttributeCode);
        self::assertSame(MappingConfidence::Auto, $suggestions[0]->confidence);
    }

    #[Test]
    public function sampleValuesAreSlicedPerColumn(): void
    {
        $mapper = $this->mapperWithDictionary([
            'sku' => ['aliases' => ['sku']],
            'name' => ['aliases' => ['name']],
        ]);

        $suggestions = $mapper->map(
            ['sku', 'name'],
            ['sku', 'name'],
            [['ABC-1', 'Foo'], ['DEF-2', 'Bar']],
        );

        self::assertSame(['ABC-1', 'DEF-2'], $suggestions[0]->sampleValues);
        self::assertSame(['Foo', 'Bar'], $suggestions[1]->sampleValues);
    }

    /**
     * @return list<string>
     */
    private function festoAvailableAttributeCodes(): array
    {
        // __category__ is the reserved target injected by AutoMapController
        // for product imports — the dictionary maps `Kategoria` and friends
        // to it, the matcher treats it like any other available code.
        return [
            'sku', 'name', 'price', 'brand', '__category__', 'ean', 'description',
            'main_image', 'gallery_2', 'gallery_3', 'ip_class', 'diameter',
            'short_description', 'weight', 'color',
        ];
    }

    private function mapperWithRealDictionary(): AutoMapper
    {
        $dictionaryPath = __DIR__.'/../../../config/imports/mapping_dictionary.yaml';
        $service = new MappingDictionaryService($dictionaryPath, new ArrayAdapter());

        return new AutoMapper($service);
    }

    /**
     * @param array<string, array{aliases: list<string>}> $entries
     */
    private function mapperWithDictionary(array $entries): AutoMapper
    {
        $dictionary = new class($entries) implements MappingDictionaryProvider {
            /**
             * @param array<string, array{aliases: list<string>}> $entries
             */
            public function __construct(private readonly array $entries)
            {
            }

            public function load(): array
            {
                $out = [];
                foreach ($this->entries as $code => $entry) {
                    $out[$code] = $entry['aliases'];
                }

                return $out;
            }

            public function aliasIndex(): array
            {
                $index = [];
                foreach ($this->entries as $code => $entry) {
                    foreach ($entry['aliases'] as $alias) {
                        $index[$alias] = $code;
                    }
                }

                return $index;
            }
        };

        return new AutoMapper($dictionary);
    }
}
