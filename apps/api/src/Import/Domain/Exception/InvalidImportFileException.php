<?php

declare(strict_types=1);

namespace App\Import\Domain\Exception;

use RuntimeException;
use Throwable;

final class InvalidImportFileException extends RuntimeException
{
    public static function unreadable(string $path): self
    {
        return new self(\sprintf('Cannot read import file "%s".', $path));
    }

    public static function unsupportedExtension(string $extension): self
    {
        return new self(\sprintf(
            'Unsupported import file extension "%s". Accepted: .xlsx, .csv.',
            $extension,
        ));
    }

    public static function corrupted(string $path, Throwable $previous): self
    {
        return new self(\sprintf('Import file "%s" is corrupted: %s', $path, $previous->getMessage()), 0, $previous);
    }

    public static function corruptedEncoding(string $encoding): self
    {
        return new self(\sprintf('Failed to convert import file from "%s" to UTF-8.', $encoding));
    }

    public static function empty(): self
    {
        return new self('Import file is empty.');
    }

    public static function noHeaderRow(): self
    {
        return new self('Import file is missing the header row.');
    }

    /**
     * AUD-066 (W3-5.2) — the file's binary signature does not match its
     * extension (e.g. a CSV body renamed to .xlsx, or a ZIP/XLSX renamed to
     * .csv). Reject before any parser touches the bytes.
     */
    public static function signatureMismatch(string $extension): self
    {
        return new self(\sprintf(
            'Import file content does not match its ".%s" extension (binary signature mismatch).',
            $extension,
        ));
    }
}
