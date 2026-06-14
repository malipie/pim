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
        // Detection runs on a fixed-size prefix (IMP2-2.1 streaming): that window
        // can split a multi-byte UTF-8 character in half. Drop a truncated
        // trailing sequence first, otherwise a clean UTF-8 file fails the strict
        // mb_check_encoding probe and is misclassified as CP1250 — which then
        // transcodes (and corrupts) the whole file.
        $probe = self::trimPartialTrailingChar($bytes);
        if (mb_check_encoding($probe, 'UTF-8')) {
            return FileEncoding::Utf8;
        }
        // Polish-letter signature byte ranges (ą=0xB9, ć=0xE6 in CP1250
        // but 0xE6 collides with ISO-8859-2 — checking the range distinguishes).
        // The cheap heuristic: if it converts cleanly through CP1250 → UTF-8
        // and the result is valid UTF-8, prefer CP1250.
        $candidate = @iconv('Windows-1250', 'UTF-8//IGNORE', $probe);
        if (false !== $candidate && mb_check_encoding($candidate, 'UTF-8')) {
            return FileEncoding::Windows1250;
        }

        return FileEncoding::Iso88592;
    }

    /**
     * Remove a trailing UTF-8 byte sequence that a fixed-size prefix read may
     * have cut mid-character, so the strict UTF-8 probe is not defeated by an
     * artefact of the window size (it leaves complete sequences — and any
     * single-byte CP1250/ISO content — untouched).
     */
    private static function trimPartialTrailingChar(string $bytes): string
    {
        $len = \strlen($bytes);
        if (0 === $len) {
            return $bytes;
        }
        $i = $len - 1;
        // Walk back over UTF-8 continuation bytes (10xxxxxx).
        while ($i >= 0 && 0x80 === (\ord($bytes[$i]) & 0xC0)) {
            --$i;
        }
        if ($i < 0) {
            return $bytes; // only continuation bytes — not a clean lead, leave it
        }
        $lead = \ord($bytes[$i]);
        $expected = match (true) {
            $lead >= 0xF0 => 4,
            $lead >= 0xE0 => 3,
            $lead >= 0xC0 => 2,
            default => 1, // ASCII or stray continuation — a complete unit
        };

        return ($len - $i) < $expected ? substr($bytes, 0, $i) : $bytes;
    }

    public function stripBom(string $bytes): string
    {
        return str_starts_with($bytes, self::BOM_UTF8)
            ? substr($bytes, \strlen(self::BOM_UTF8))
            : $bytes;
    }
}
