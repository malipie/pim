<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Media;

use App\Import\Application\Service\Media\ZipImageExtractor;
use Normalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * IMP2-1.13 — ZIP entry extraction: case-insensitive + Unicode-normalisation
 * matching, subdirectories, traversal rejection, zip-bomb guard.
 */
final class ZipImageExtractorTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    #[Test]
    public function resolvesCaseInsensitivelyAcrossSubdirAndBasename(): void
    {
        $zip = $this->makeZip([
            'images/Zdjecie1.JPG' => $this->png(),
            'flat.png' => $this->png(),
        ]);
        $extractor = new ZipImageExtractor($zip);
        try {
            // basename, different case
            self::assertSame('images/Zdjecie1.JPG', $extractor->resolve('zdjecie1.jpg'));
            // full relative path, different case
            self::assertSame('images/Zdjecie1.JPG', $extractor->resolve('IMAGES/zdjecie1.JPG'));
            self::assertSame('flat.png', $extractor->resolve('FLAT.PNG'));
            self::assertNull($extractor->resolve('missing.png'));
        } finally {
            $extractor->close();
        }
    }

    #[Test]
    public function extractsEntryToTempPreservingBytes(): void
    {
        $zip = $this->makeZip(['a.png' => $this->png()]);
        $extractor = new ZipImageExtractor($zip);
        try {
            $tmp = $extractor->extractToTemp('a.png');
            self::assertIsString($tmp);
            $this->tmpFiles[] = $tmp;
            self::assertSame($this->png(), file_get_contents($tmp));
            self::assertNull($extractor->extractToTemp('nope.png'));
        } finally {
            $extractor->close();
        }
    }

    #[Test]
    public function matchesPolishFilenameAcrossUnicodeNormalisation(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('intl Normalizer not available');
        }
        $nfc = Normalizer::normalize('żółć.png', Normalizer::FORM_C);
        $nfd = Normalizer::normalize('żółć.png', Normalizer::FORM_D);
        self::assertIsString($nfc);
        self::assertIsString($nfd);

        // Store under NFC, look up with NFD (and vice-versa).
        $zip = $this->makeZip([$nfc => $this->png()]);
        $extractor = new ZipImageExtractor($zip);
        try {
            self::assertNotNull($extractor->resolve($nfd), 'NFD query must match an NFC-stored entry');
            self::assertNotNull($extractor->resolve($nfc));
        } finally {
            $extractor->close();
        }
    }

    #[Test]
    public function traversalAndAbsoluteEntriesAreNotIndexed(): void
    {
        $zip = $this->makeZip([
            '../evil.png' => $this->png(),
            'safe.png' => $this->png(),
        ]);
        $extractor = new ZipImageExtractor($zip);
        try {
            self::assertNull($extractor->resolve('../evil.png'));
            self::assertNull($extractor->resolve('evil.png'));
            self::assertNotNull($extractor->resolve('safe.png'));
        } finally {
            $extractor->close();
        }
    }

    #[Test]
    public function zipBombRatioIsRejected(): void
    {
        // 1 MiB of a single repeated byte compresses to ~KB → ratio >> 200.
        $bomb = str_repeat('A', 1024 * 1024);
        $zip = $this->makeZip(['bomb.bin' => $bomb]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/zip bomb/i');
        new ZipImageExtractor($zip);
    }

    private function png(): string
    {
        $bytes = base64_decode(self::PNG, true);
        \assert(false !== $bytes);

        return $bytes;
    }

    /**
     * @param array<string, string> $entries name => contents
     */
    private function makeZip(array $entries): string
    {
        // tempnam() creates the file, so OVERWRITE (overwrite-existing) is the
        // portable open mode — CREATE|OVERWRITE on a non-existent `.zip` path
        // errors on some libzip builds (CI). Throw explicitly because assert is
        // a no-op when zend.assertions is off (CI), which would otherwise
        // surface only as a later "Invalid or uninitialized Zip object".
        $path = tempnam(sys_get_temp_dir(), 'pim-ziptest-');
        if (false === $path) {
            throw new RuntimeException('tempnam() failed for the test ZIP.');
        }
        $this->tmpFiles[] = $path;
        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::OVERWRITE);
        if (true !== $opened) {
            throw new RuntimeException('Failed to create test ZIP: '.var_export($opened, true));
        }
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }
}
