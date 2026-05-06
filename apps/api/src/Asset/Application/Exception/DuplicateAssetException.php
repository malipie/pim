<?php

declare(strict_types=1);

namespace App\Asset\Application\Exception;

use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * Thrown by `UploadAssetHandler` when the SHA-256 hash of the uploaded
 * bytes matches an existing Asset for the current tenant. The HTTP layer
 * maps this to 409 Conflict + a Problem Details payload pointing at the
 * existing asset.
 */
final class DuplicateAssetException extends RuntimeException
{
    public function __construct(public readonly Uuid $existingAssetId, public readonly string $existingCode)
    {
        parent::__construct(\sprintf(
            'Duplicate asset detected — content already stored as "%s" (%s).',
            $existingCode,
            $existingAssetId->toRfc4122(),
        ));
    }
}
