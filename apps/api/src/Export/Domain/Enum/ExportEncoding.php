<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * CSV encoding choice (PRD §8.4). Ignored for XLSX (always UTF-8).
 *
 * UTF-8 with BOM is the default — Excel PL on Windows detects the byte
 * order mark and renders polish characters correctly. Windows-1250 is
 * kept as a legacy option for clients with older toolchains.
 */
enum ExportEncoding: string
{
    case Utf8Bom = 'utf8_bom';
    case Windows1250 = 'windows_1250';

    public function bomBytes(): string
    {
        return match ($this) {
            self::Utf8Bom => "\xEF\xBB\xBF",
            self::Windows1250 => '',
        };
    }
}
