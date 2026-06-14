<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\HeaderNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * IMP2-2.1 — duplicate non-empty headers get occurrence suffixes; blanks stay
 * positional blanks (D12).
 */
final class HeaderNormalizerTest extends TestCase
{
    #[Test]
    public function suffixesDuplicateNonEmptyHeaders(): void
    {
        self::assertSame(
            ['sku', 'color', 'size', 'color#2', 'price'],
            HeaderNormalizer::deduplicate(['sku', 'color', 'size', 'color', 'price']),
        );
    }

    #[Test]
    public function tripleDuplicateGetsSequentialSuffixes(): void
    {
        self::assertSame(['a', 'a#2', 'a#3'], HeaderNormalizer::deduplicate(['a', 'a', 'a']));
    }

    #[Test]
    public function blanksAndNullsStayPositionalEmpty(): void
    {
        self::assertSame(['sku', '', 'name', ''], HeaderNormalizer::deduplicate(['sku', null, 'name', '  ']));
    }

    #[Test]
    public function trimsLabelsBeforeComparing(): void
    {
        self::assertSame(['ean', 'ean#2'], HeaderNormalizer::deduplicate([' ean ', 'ean']));
    }

    #[Test]
    public function generatedSuffixDoesNotCollideWithLiteralUserColumn(): void
    {
        // A duplicate `color` would naively become `color#2` and overwrite a
        // real user column literally named `color#2` — silent data loss
        // (IMP2-2.1 review). The suffix must bump past any already-used label.
        self::assertSame(
            ['color', 'color#2', 'color#2#2'],
            HeaderNormalizer::deduplicate(['color', 'color', 'color#2']),
        );
        // …and the reverse order (literal first, then the duplicates).
        self::assertSame(
            ['color#2', 'color', 'color#3'],
            HeaderNormalizer::deduplicate(['color#2', 'color', 'color']),
        );
        // Every output label is unique → no positional value can be overwritten.
        $out = HeaderNormalizer::deduplicate(['color', 'color', 'color#2', 'color', 'color#2']);
        self::assertSame($out, array_values(array_unique($out)));
    }
}
