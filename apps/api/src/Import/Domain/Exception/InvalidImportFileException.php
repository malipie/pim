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
}
