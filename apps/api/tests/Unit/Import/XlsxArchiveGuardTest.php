<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\Archive\ArchiveSecurityException;
use App\Import\Application\Service\Archive\XlsxArchiveGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * IMP2-2.8 (#1484) — zip-bomb guard. Inspects the ZIP central directory only
 * (no decompression) and rejects bombs before the parser runs. The ratio check
 * is conjunctive: high ratio alone (repeated values) must NOT false-positive.
 */
final class XlsxArchiveGuardTest extends TestCase
{
    private string $path = '';

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pim-xlsx-');
        self::assertIsString($tmp);
        $this->path = $tmp.'.xlsx';
        @rename($tmp, $this->path);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    /**
     * @param array<string, string> $entries
     */
    private function makeZip(array $entries): void
    {
        $zip = new ZipArchive();
        $zip->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    #[Test]
    public function legitimateArchivePasses(): void
    {
        $this->makeZip(['[Content_Types].xml' => '<types/>', 'xl/worksheets/sheet1.xml' => '<sheet/>']);
        new XlsxArchiveGuard()->validate($this->path);
        self::addToAssertionCount(1); // reached here = no rejection
    }

    #[Test]
    public function highRatioButSmallArchivePasses(): void
    {
        // Compresses ~1000:1 but the absolute decompressed size stays tiny, so the
        // conjunctive ratio check (ratio AND >floor) must not trip.
        $this->makeZip(['repeated.xml' => str_repeat('A', 5_000)]);
        new XlsxArchiveGuard(maxRatio: 10, ratioFloorBytes: 512 * 1024 * 1024)->validate($this->path);
        self::addToAssertionCount(1); // reached here = no rejection
    }

    #[Test]
    public function rejectsTooManyEntries(): void
    {
        $entries = [];
        for ($i = 0; $i < 5; ++$i) {
            $entries["e{$i}.xml"] = 'x';
        }
        $this->makeZip($entries);

        $this->expectException(ArchiveSecurityException::class);
        new XlsxArchiveGuard(maxEntries: 3)->validate($this->path);
    }

    #[Test]
    public function rejectsExcessiveUncompressedSize(): void
    {
        $this->makeZip(['big.xml' => str_repeat('A', 100_000)]);

        $this->expectException(ArchiveSecurityException::class);
        new XlsxArchiveGuard(maxUncompressedBytes: 1_000)->validate($this->path);
    }

    #[Test]
    public function rejectsHighRatioBomb(): void
    {
        $this->makeZip(['bomb.xml' => str_repeat('A', 1_000_000)]);

        $this->expectException(ArchiveSecurityException::class);
        new XlsxArchiveGuard(maxRatio: 50, ratioFloorBytes: 500_000)->validate($this->path);
    }

    #[Test]
    public function rejectsNonZipFile(): void
    {
        file_put_contents($this->path, 'definitely not a zip archive');

        $this->expectException(ArchiveSecurityException::class);
        new XlsxArchiveGuard()->validate($this->path);
    }
}
