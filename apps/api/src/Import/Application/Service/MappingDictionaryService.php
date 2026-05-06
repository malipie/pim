<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Loads the rules-based PL/EN auto-mapping dictionary from
 * `config/imports/mapping_dictionary.yaml`. Cached for 5 minutes
 * because it lives in YAML and never changes between deploys —
 * re-reading it on every wizard step is wasted I/O.
 *
 * Output shape: attribute_code → list<normalised alias>. Aliases are
 * already normalised in the YAML (lowercase + alnum-only), so callers
 * can lookup directly without re-processing.
 */
final readonly class MappingDictionaryService implements MappingDictionaryProvider
{
    public function __construct(
        private string $dictionaryPath,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function load(): array
    {
        return $this->cache->get('imports.mapping_dictionary', function (ItemInterface $item): array {
            $item->expiresAfter(300);

            return $this->parseFile();
        });
    }

    /**
     * Reverse index: alias → attribute_code. The matcher hits this once
     * per column header so it's worth precomputing.
     *
     * @return array<string, string>
     */
    public function aliasIndex(): array
    {
        return $this->cache->get('imports.mapping_dictionary.alias_index', function (ItemInterface $item): array {
            $item->expiresAfter(300);

            $index = [];
            foreach ($this->parseFile() as $attributeCode => $aliases) {
                foreach ($aliases as $alias) {
                    $index[$alias] = $attributeCode;
                }
            }

            return $index;
        });
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseFile(): array
    {
        if (!is_readable($this->dictionaryPath)) {
            return [];
        }

        /** @var array{attributes?: array<string, array{aliases?: list<string>}>}|null $raw */
        $raw = Yaml::parseFile($this->dictionaryPath);
        if (null === $raw || !isset($raw['attributes'])) {
            return [];
        }

        $dictionary = [];
        foreach ($raw['attributes'] as $code => $entry) {
            $aliases = $entry['aliases'] ?? [];
            $normalised = [];
            foreach ($aliases as $alias) {
                $normalised[] = strtolower($alias);
            }
            $dictionary[$code] = array_values(array_unique($normalised));
        }

        return $dictionary;
    }
}
