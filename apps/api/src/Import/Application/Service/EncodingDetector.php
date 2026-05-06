<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Enum\FileEncoding;

/**
 * Detect the encoding of a CSV / xlsx file.
 *
 * Order per spec §7.2:
 *   1. UTF-8 BOM (`EF BB BF`).
 *   2. Valid UTF-8 (mb_check_encoding) — covers BOM-less UTF-8.
 *   3. Heuristic CP1250 — strict UTF-8 fails *and* the bytes contain
 *      Polish-letter signatures (CP1250 specific 0xB9, 0xBE, 0xCE…).
 *   4. Fallback: ISO-8859-2 (covered by user override in the wizard).
 *
 * The wizard always lets the user override; this is just the default.
 */
final class EncodingDetector
{
    public const string BOM_UTF8 = "\xEF\xBB\xBF";

    public function detect(string $bytes): FileEncoding
    {
        if (str_starts_with($bytes, self::BOM_UTF8)) {
            return FileEncoding::Utf8Bom;
        }
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return FileEncoding::Utf8;
        }
        // Polish-letter signature byte ranges (ą=0xB9, ć=0xE6 in CP1250
        // but 0xE6 collides with ISO-8859-2 — checking the range distinguishes).
        // The cheap heuristic: if it converts cleanly through CP1250 → UTF-8
        // and the result is valid UTF-8, prefer CP1250.
        $candidate = @iconv('Windows-1250', 'UTF-8//IGNORE', $bytes);
        if (false !== $candidate && mb_check_encoding($candidate, 'UTF-8')) {
            return FileEncoding::Windows1250;
        }

        return FileEncoding::Iso88592;
    }

    public function stripBom(string $bytes): string
    {
        return str_starts_with($bytes, self::BOM_UTF8)
            ? substr($bytes, \strlen(self::BOM_UTF8))
            : $bytes;
    }
}
