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

    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    public ?string $code = null;

    #[Assert\Choice(choices: ['CREATE', 'UPDATE', 'UPSERT'])]
    public ?string $mode = null;

    /**
     * @var array<string, string>|null
     */
    public ?array $columnMapping = null;

    public ?string $locale = null;

    public ?string $encoding = null;

    public ?string $delimiter = null;

    public ?string $imageSource = null;

    public ?string $imageZipNamingConvention = null;

    /**
     * IMP2-2.7 (#1483) — error-rate abort threshold (percent of processed
     * rows). null in a merge-patch means "leave unchanged".
     */
    #[Assert\Range(min: 0, max: 100)]
    public ?int $allowedErrorsPct = null;
}
