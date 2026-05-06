<?php

declare(strict_types=1);

namespace App\Backup\Application\Service;

final readonly class BackupRunResult
{
    public function __construct(
        public bool $success,
        public int $sizeBytes,
        public ?string $pgbackrestLabel,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(int $sizeBytes, ?string $label = null): self
    {
        return new self(true, $sizeBytes, $label);
    }

    public static function failure(string $message): self
    {
        return new self(false, 0, null, $message);
    }
}
