<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use App\Catalog\Domain\Repository\AttributeOptionRepositoryInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * #1718 — resolves a raw select/multiselect import cell to the canonical
 * {@see AttributeOption} code to store, optionally minting a new option when
 * the raw value matches none.
 *
 * Import files from external systems (IdoSell/IAI, Shopify CSV) carry option
 * VALUES as human labels ("Beżowy"), not the system's option CODES, so the
 * {@see \App\Catalog\Application\Validation\TypeValidator\SelectValidator}
 * rejects every unknown label and the row fails. This service maps label →
 * code and, when enabled, creates the missing option so the import "just
 * works". The behaviour is gated by {@see \App\Import\Domain\Entity\ImportSession::createMissingOptions()}
 * (opt-in, data governance): off = unchanged strict behaviour.
 *
 * Match order (case-insensitive): existing code === raw, then any label
 * locale === raw. Miss + create=true → mint `AttributeOption(code = slug(raw),
 * label {pl: raw})`; the repository save() flushes, so the value-write
 * validation (which queries `attribute_options`) sees the new code in the
 * same run. Miss + create=false → return raw unchanged (validator then flags
 * the row, preserving the pre-flag contract).
 *
 * Per-attribute lookups are cached as PLAIN DATA (code set, label→code map,
 * max position) so they survive the import handler's EntityManager::clear()
 * at chunk boundaries without holding detached entity references. The cache
 * is reset per run ({@see reset()}) to avoid cross-run staleness in the
 * long-lived FrankenPHP worker.
 */
final class OptionAutoCreator
{
    private readonly SluggerInterface $slugger;

    /**
     * @var array<string, array{codes: array<string, true>, byLabel: array<string, string>, maxPosition: int}>
     *                                                                                                         keyed by attribute id (RFC 4122)
     */
    private array $cache = [];

    public function __construct(
        private readonly AttributeOptionRepositoryInterface $options,
        ?SluggerInterface $slugger = null,
    ) {
        $this->slugger = $slugger ?? new AsciiSlugger();
    }

    /**
     * Drops the per-attribute cache. The import handler calls this once at the
     * start of each run so a fresh message never serves option data minted /
     * read during a previous run on the same worker.
     */
    public function reset(): void
    {
        $this->cache = [];
    }

    public function resolve(Attribute $attribute, string $rawValue, bool $create): string
    {
        $raw = trim($rawValue);
        if ('' === $raw) {
            return $raw;
        }

        $index = $this->indexFor($attribute);

        // 1) exact code match — round-trips PIM's own exports (codes verbatim).
        if (isset($index['codes'][$raw])) {
            return $raw;
        }
        // 2) label match (any locale, case-insensitive) — external exports.
        $labelKey = mb_strtolower($raw);
        if (isset($index['byLabel'][$labelKey])) {
            return $index['byLabel'][$labelKey];
        }

        if (!$create) {
            return $raw;
        }

        return $this->mint($attribute, $raw);
    }

    private function mint(Attribute $attribute, string $raw): string
    {
        $key = $attribute->getId()->toRfc4122();
        $index = $this->cache[$key];

        $code = $this->uniqueCode($raw, $index['codes']);
        $option = new AttributeOption(
            attribute: $attribute,
            code: $code,
            label: ['pl' => $raw],
            position: $index['maxPosition'] + 1,
        );
        // save() persists + flushes, so the value-write validation later in the
        // same row sees the new code. Minting is rare (only genuinely new
        // values when the flag is on), so the extra flush is bounded.
        $this->options->save($option);

        $index['codes'][$code] = true;
        $index['byLabel'][mb_strtolower($raw)] = $code;
        ++$index['maxPosition'];
        $this->cache[$key] = $index;

        return $code;
    }

    /**
     * @param array<string, true> $existingCodes
     */
    private function uniqueCode(string $raw, array $existingCodes): string
    {
        $base = $this->slugger->slug($raw, '_')->lower()->toString();
        if ('' === $base) {
            $base = 'option';
        }
        $base = mb_substr($base, 0, 60);

        $code = $base;
        $suffix = 2;
        while (isset($existingCodes[$code])) {
            $code = $base.'_'.$suffix;
            ++$suffix;
        }

        return $code;
    }

    /**
     * @return array{codes: array<string, true>, byLabel: array<string, string>, maxPosition: int}
     */
    private function indexFor(Attribute $attribute): array
    {
        $key = $attribute->getId()->toRfc4122();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $codes = [];
        $byLabel = [];
        $maxPosition = -1;
        foreach ($this->options->findByAttribute($attribute) as $option) {
            $codes[$option->getCode()] = true;
            foreach ($option->getLabel() as $label) {
                if ('' !== $label) {
                    $byLabel[mb_strtolower($label)] = $option->getCode();
                }
            }
            $maxPosition = max($maxPosition, $option->getPosition());
        }

        return $this->cache[$key] = ['codes' => $codes, 'byLabel' => $byLabel, 'maxPosition' => $maxPosition];
    }
}
