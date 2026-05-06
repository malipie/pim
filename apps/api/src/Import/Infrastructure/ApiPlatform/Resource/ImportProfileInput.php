<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Wire payload for {@see \App\Import\Domain\Entity\ImportProfile} POST.
 *
 * Mapped through {@see \App\Import\Infrastructure\ApiPlatform\State\ImportProfileProcessor},
 * which resolves the target ObjectType, stamps user/tenant from the
 * security token, and persists the row. The DTO stays plain — Domain
 * entity ctor enforces invariants.
 */
final class ImportProfileInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $targetObjectTypeId = '';

    /**
     * @var array<string, string>
     */
    public array $columnMapping = [];

    public ?string $locale = null;

    public ?string $encoding = null;

    public ?string $delimiter = null;

    /**
     * One of: `http`, `zip`, `none`.
     */
    public ?string $imageSource = null;

    public ?string $imageZipNamingConvention = null;
}
