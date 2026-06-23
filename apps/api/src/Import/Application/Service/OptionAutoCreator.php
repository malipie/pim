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
    /** AttributeOption.code DB column length (Assert\Length max). */
    private const int MAX_CODE_LENGTH = 64;

    /**
     * Defensive ceiling on options minted per run. A free-text column mistakenly
     * mapped to a select with the flag on would otherwise mint one option per
     * distinct value (potentially 50k+). Past the cap, resolve() stops minting
     * and returns the raw value so the validator flags those rows instead.
     */
    private const int MAX_MINTS_PER_RUN = 10_000;

    private readonly SluggerInterface $slugger;

    /**
     * @var array<string, array{codes: array<string, string>, byLabel: array<string, string>, maxPosition: int}>
     *                                                                                                           keyed by attribute id (RFC 4122). `codes` maps a LOWER-CASED code to
     *                                                                                                           its canonical form; `byLabel` maps a lower-cased label to a code.
     */
    private array $cache = [];

    private int $mintCount = 0;

    public function __construct(
        private readonly AttributeOptionRepositoryInterface $options,
        ?SluggerInterface $slugger = null,
    ) {
        $this->slugger = $slugger ?? new AsciiSlugger();
    }

    /**
     * Drops the per-attribute cache and mint counter. The import handler calls
     * this once at the start of each run so a fresh message never serves option
     * data minted / read during a previous run on the same worker.
     */
    public function reset(): void
    {
        $this->cache = [];
        $this->mintCount = 0;
    }

    public function resolve(Attribute $attribute, string $rawValue, bool $create): string
    {
        $raw = trim($rawValue);
        if ('' === $raw) {
            return $raw;
        }

        $index = $this->indexFor($attribute);
        $key = mb_strtolower($raw);

        // 1) code match (case-insensitive) → canonical code. Takes priority over
        // labels and round-trips PIM's own exports (which store codes). The
        // lookup is lower-cased so an upper-cased external code resolves to its
        // canonical form instead of hijacking another option that merely carries
        // a colliding label, or minting a near-duplicate.
        if (isset($index['codes'][$key])) {
            return $index['codes'][$key];
        }
        // 2) label match (any locale, case-insensitive) — external exports.
        if (isset($index['byLabel'][$key])) {
            return $index['byLabel'][$key];
        }

        if (!$create || $this->mintCount >= self::MAX_MINTS_PER_RUN) {
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
        // same row sees the new code. ImportObjectCreator builds the writes
        // (this mint) BEFORE persisting the in-progress object, so the flush only
        // commits already-complete prior rows plus the new option — never a
        // half-built current object. Minting is rare (genuinely new values).
        $this->options->save($option);
        ++$this->mintCount;

        $index['codes'][mb_strtolower($code)] = $code;
        $index['byLabel'][mb_strtolower($raw)] = $code;
        ++$index['maxPosition'];
        $this->cache[$key] = $index;

        return $code;
    }

    /**
     * @param array<string, string> $existingCodes lower-cased code => canonical
     */
    private function uniqueCode(string $raw, array $existingCodes): string
    {
        $base = $this->slugger->slug($raw, '_')->lower()->toString();
        if ('' === $base) {
            $base = 'option';
        }
        $base = mb_substr($base, 0, self::MAX_CODE_LENGTH);

        $code = $base;
        $suffix = 2;
        while (isset($existingCodes[mb_strtolower($code)])) {
            $tag = '_'.$suffix;
            $code = mb_substr($base, 0, self::MAX_CODE_LENGTH - mb_strlen($tag)).$tag;
            ++$suffix;
        }

        return $code;
    }

    /**
     * @return array{codes: array<string, string>, byLabel: array<string, string>, maxPosition: int}
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
            $codes[mb_strtolower($option->getCode())] = $option->getCode();
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
