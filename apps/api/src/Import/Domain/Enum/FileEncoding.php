<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

enum FileEncoding: string
{
    case Utf8 = 'utf-8';
    case Utf8Bom = 'utf-8-bom';
    case Windows1250 = 'windows-1250';
    case Iso88592 = 'iso-8859-2';

    public function iconvName(): string
    {
        return match ($this) {
            self::Utf8, self::Utf8Bom => 'UTF-8',
            self::Windows1250 => 'Windows-1250',
            self::Iso88592 => 'ISO-8859-2',
        };
    }
}
