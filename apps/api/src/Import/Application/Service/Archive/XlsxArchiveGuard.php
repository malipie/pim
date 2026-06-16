<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Archive;

use ZipArchive;

/**
 * IMP2-2.8 (#1484) — zip-bomb guard for uploaded XLSX files. An XLSX is a ZIP;
 * a crafted archive can inflate to gigabytes and OOM the 256 MiB worker before
 * the parser even runs. This inspects the ZIP central directory via
 * {@see ZipArchive::statIndex()} — metadata only, NO decompression — and rejects
 * before any parse touches the bytes.
 *
 * Thresholds are intentionally CONJUNCTIVE for the ratio check: legitimate files
 * with thousands of repeated values compress extremely well, so a high ratio
 * alone must not reject — only a high ratio AND a large absolute decompressed
 * size. Defaults are generous (D10 export tops out far below them); override via
 * the constructor (services.yaml) per deployment.
 */
final readonly class XlsxArchiveGuard
{
    public function __construct(
        private int $maxEntries = 1_000,
        private int $maxUncompressedBytes = 2 * 1024 * 1024 * 1024,
        private int $maxRatio = 200,
        private int $ratioFloorBytes = 512 * 1024 * 1024,
    ) {
    }

    /**
     * @throws ArchiveSecurityException when the archive is unreadable as a ZIP
     *                                  or trips a zip-bomb threshold
     */
    public function validate(string $path): void
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::RDONLY);
        if (true !== $opened) {
            // A valid XLSX is always a readable ZIP. Anything else here is
            // corrupt or hostile — reject rather than hand it to the parser.
            throw $this->reject();
        }

        try {
            $count = $zip->count();
            if ($count > $this->maxEntries) {
                throw $this->reject();
            }

            $totalUncompressed = 0;
            $totalCompressed = 0;
            for ($i = 0; $i < $count; ++$i) {
                $stat = $zip->statIndex($i);
                if (false === $stat) {
                    continue;
                }
                $totalUncompressed += $stat['size'];
                $totalCompressed += $stat['comp_size'];
            }

            if ($totalUncompressed > $this->maxUncompressedBytes) {
                throw $this->reject();
            }

            // Ratio check only bites when the absolute decompressed size is also
            // large — high-ratio-but-small archives (e.g. repeated values) pass.
            if ($totalCompressed > 0
                && $totalUncompressed > $this->ratioFloorBytes
                && ($totalUncompressed / $totalCompressed) > $this->maxRatio
            ) {
                throw $this->reject();
            }
        } finally {
            $zip->close();
        }
    }

    private function reject(): ArchiveSecurityException
    {
        return new ArchiveSecurityException(
            'Plik wygląda na uszkodzony lub potencjalnie niebezpieczny (nadmierny '
            .'współczynnik kompresji). Przekonwertuj plik do CSV i spróbuj ponownie.',
        );
    }
}
