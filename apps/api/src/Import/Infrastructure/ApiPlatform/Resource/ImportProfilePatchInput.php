<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Patch DTO — every field optional. The processor only writes the
 * ones the caller actually included in the JSON merge-patch payload.
 */
final class ImportProfilePatchInput
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    /**
     * @var array<string, string>|null
     */
    public ?array $columnMapping = null;

    public ?string $locale = null;

    public ?string $encoding = null;

    public ?string $delimiter = null;

    public ?string $imageSource = null;

    public ?string $imageZipNamingConvention = null;
}
