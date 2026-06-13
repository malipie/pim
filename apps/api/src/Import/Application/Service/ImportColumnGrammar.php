<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Channel\Contracts\ScopeEnumeratorInterface;
use App\Import\Domain\ValueObject\ParsedColumnHeader;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-1.6 (#1469, ADR-0019) — the authoritative import-side column
 * grammar: `code` | `code.locale` | `code.channel` | `code.locale.channel`.
 *
 * Suffix segments are disambiguated against the tenant registries (active
 * locales, channel codes) instead of being blindly read as a locale — the
 * pre-1.6 behaviour silently corrupted channel-scoped exports into rows
 * with locale='shopify'. On a code collision (a channel named like an
 * active locale) the LOCALE wins per ADR-0019 and the parse carries a
 * collision flag the validator surfaces as a warning. Unknown suffixes
 * are a column-level error, never a silent locale.
 *
 * The registry is read through {@see ScopeEnumeratorInterface}
 * (Channel\Contracts) and cached per tenant, so the channel code → id
 * resolution happens once per import session rather than once per row
 * (item 3). The resolved {@see Uuid} rides on {@see ParsedColumnHeader}
 * straight to the value writer.
 */
final class ImportColumnGrammar
{
    /** @var array<string, array{locales: array<string, true>, channels: array<string, Uuid>}> */
    private array $registry = [];

    public function __construct(
        private readonly ScopeEnumeratorInterface $scopes,
    ) {
    }

    public function parse(string $header, Tenant $tenant): ParsedColumnHeader
    {
        $segments = explode('.', $header);
        $base = array_shift($segments);

        if ([] === $segments) {
            return new ParsedColumnHeader($base, null, null);
        }

        ['locales' => $locales, 'channels' => $channels] = $this->registryFor($tenant);

        if (1 === \count($segments)) {
            $suffix = $segments[0];
            $isLocale = isset($locales[$suffix]);
            $channelId = $channels[$suffix] ?? null;

            if ($isLocale) {
                // ADR-0019 precedence: locale wins; flag the ambiguity so
                // dry-run can warn when a channel shares the code. The
                // stored locale is the short language code already
                // (ScopeEnumerator normalises 'en_US' → 'en').
                return new ParsedColumnHeader($base, $suffix, null, localeChannelCollision: null !== $channelId);
            }
            if (null !== $channelId) {
                return new ParsedColumnHeader($base, null, $suffix, $channelId);
            }

            return ParsedColumnHeader::unknownSuffix($base, $suffix);
        }

        if (2 === \count($segments)) {
            [$locale, $channel] = $segments;
            $channelId = $channels[$channel] ?? null;
            if (isset($locales[$locale]) && null !== $channelId) {
                return new ParsedColumnHeader($base, $locale, $channel, $channelId);
            }

            return ParsedColumnHeader::unknownSuffix($base, implode('.', $segments));
        }

        return ParsedColumnHeader::unknownSuffix($base, implode('.', $segments));
    }

    /**
     * The attribute-code segment of a header (everything before the first
     * dot). Tenant-free, because attribute codes never contain a dot
     * (enforced by the Attribute code charset) — the first dot always
     * separates the code from its locale/channel modifiers. Used by the
     * auto-mapper, which matches on the bare code without needing the
     * tenant registry.
     */
    public static function baseOf(string $header): string
    {
        $pos = strpos($header, '.');

        return false === $pos ? $header : substr($header, 0, $pos);
    }

    /**
     * @return array{locales: array<string, true>, channels: array<string, Uuid>}
     */
    private function registryFor(Tenant $tenant): array
    {
        $key = $tenant->getId()->toRfc4122();
        if (!isset($this->registry[$key])) {
            $locales = [];
            foreach ($this->scopes->localeShortCodes($tenant) as $short) {
                $locales[$short] = true;
            }
            $channels = [];
            foreach ($this->scopes->channelIdsByCode($tenant) as $code => $id) {
                $channels[$code] = Uuid::fromString($id);
            }
            $this->registry[$key] = ['locales' => $locales, 'channels' => $channels];
        }

        return $this->registry[$key];
    }
}
