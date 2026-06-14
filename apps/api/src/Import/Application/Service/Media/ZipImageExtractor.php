<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Media;

use Normalizer;
use RuntimeException;
use ZipArchive;

use const PATHINFO_BASENAME;

/**
 * IMP2-1.13 — extracts single image entries from an import ZIP by filename,
 * streamed (never extractTo() the whole archive, never load it into RAM).
 *
 * Opens the archive once and builds a case-insensitive name index keyed by BOTH
 * the full relative path and the basename, each registered in Unicode NFC and
 * NFD form (a cell may carry `żółć.png` while the archive — zipped on macOS —
 * stored the NFD bytes, or vice-versa).
 *
 * Hardened against malicious archives (coordination point with IMP2-2.8):
 *   - path traversal / absolute / symlink entries are skipped at index time;
 *   - zip-bomb caps — max entries, max total uncompressed size, max per-entry
 *     compression ratio — throw {@see RuntimeException} (the run treats it as a
 *     systemic failure, not a per-row error).
 */
final class ZipImageExtractor
{
    private const int MAX_ENTRIES = 50_000;
    private const int MAX_TOTAL_UNCOMPRESSED = 2 * 1024 * 1024 * 1024; // 2 GiB
    private const int MAX_RATIO = 200; // uncompressed/compressed per entry
    private const int CHUNK = 65536;

    private ZipArchive $zip;

    /** @var array<string, string> normalised lookup key → real archive entry name */
    private array $index = [];

    public function __construct(string $zipPath)
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::RDONLY);
        if (true !== $opened) {
            throw new RuntimeException(\sprintf('Failed to open ZIP "%s" (code %s).', $zipPath, (string) $opened));
        }
        $this->zip = $zip;
        $this->buildIndex();
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * Resolve a cell value (filename or relative path) to a real archive entry,
     * case-insensitively and Unicode-normalisation-insensitively.
     */
    public function resolve(string $name): ?string
    {
        $trimmed = trim($name);
        foreach ($this->lookupKeys($trimmed) as $key) {
            if (isset($this->index[$key])) {
                return $this->index[$key];
            }
        }

        return null;
    }

    /**
     * Stream a resolved entry to a local temp file. Returns the temp path, or
     * null when the entry is absent. Caller unlinks.
     */
    public function extractToTemp(string $name): ?string
    {
        $entry = $this->resolve($name);
        if (null === $entry) {
            return null;
        }

        $stream = $this->zip->getStream($entry);
        if (false === $stream) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pim-zip-');
        if (false === $tmp) {
            fclose($stream);

            throw new RuntimeException('Failed to allocate a temp file for ZIP extraction.');
        }
        $out = fopen($tmp, 'w');
        if (false === $out) {
            fclose($stream);

            throw new RuntimeException('Failed to open the ZIP extraction temp file.');
        }
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK);
                if (false === $chunk) {
                    break;
                }
                fwrite($out, $chunk);
            }
        } finally {
            fclose($stream);
            fclose($out);
        }

        return $tmp;
    }

    private function buildIndex(): void
    {
        $count = $this->zip->count();
        if ($count > self::MAX_ENTRIES) {
            throw new RuntimeException(\sprintf('ZIP has %d entries, exceeding the %d limit (possible zip bomb).', $count, self::MAX_ENTRIES));
        }

        $totalUncompressed = 0;
        for ($i = 0; $i < $count; ++$i) {
            $stat = $this->zip->statIndex($i);
            if (false === $stat) {
                continue;
            }
            $entryName = $stat['name'];

            // Directories + unsafe paths never enter the index.
            if (str_ends_with($entryName, '/')) {
                continue;
            }
            if ($this->isUnsafePath($entryName)) {
                continue;
            }

            $uncompressed = $stat['size'];
            $compressed = $stat['comp_size'];
            $totalUncompressed += $uncompressed;
            if ($totalUncompressed > self::MAX_TOTAL_UNCOMPRESSED) {
                throw new RuntimeException('ZIP uncompressed size exceeds the limit (possible zip bomb).');
            }
            if ($compressed > 0 && $uncompressed / $compressed > self::MAX_RATIO) {
                throw new RuntimeException(\sprintf('ZIP entry "%s" has a suspicious compression ratio (possible zip bomb).', $entryName));
            }

            $basename = pathinfo($entryName, PATHINFO_BASENAME);
            foreach ([$entryName, $basename] as $candidate) {
                foreach ($this->lookupKeys($candidate) as $key) {
                    // First writer wins for the full path; basename collisions
                    // keep the first occurrence (deterministic).
                    $this->index[$key] ??= $entryName;
                }
            }
        }
    }

    /**
     * Lookup keys for a name: lower-cased, in NFC and NFD normalisation.
     *
     * @return list<string>
     */
    private function lookupKeys(string $name): array
    {
        $lower = mb_strtolower($name);
        $keys = [$lower];
        if (class_exists(Normalizer::class)) {
            $nfc = Normalizer::normalize($lower, Normalizer::FORM_C);
            $nfd = Normalizer::normalize($lower, Normalizer::FORM_D);
            if (\is_string($nfc)) {
                $keys[] = $nfc;
            }
            if (\is_string($nfd)) {
                $keys[] = $nfd;
            }
        }

        return array_values(array_unique($keys));
    }

    private function isUnsafePath(string $entryName): bool
    {
        if (str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
            return true;
        }
        if (1 === preg_match('#^[a-zA-Z]:[\\\\/]#', $entryName)) {
            return true; // Windows absolute (C:\…)
        }
        $parts = preg_split('#[\\\\/]#', $entryName);
        if (false === $parts) {
            return false;
        }

        return \in_array('..', $parts, true);
    }
}
