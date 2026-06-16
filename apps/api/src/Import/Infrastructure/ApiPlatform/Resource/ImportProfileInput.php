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

    /**
     * Optional — if omitted, the processor slugifies the name.
     * Must be unique per (tenant, user).
     */
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    public ?string $code = null;

    /**
     * One of: `ADD`, `UPDATE`, `UPSERT`, `MERGE`, `INCREMENT`, `DELETE`.
     */
    #[Assert\Choice(choices: ['CREATE', 'UPDATE', 'UPSERT'])]
    public ?string $mode = null;

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

    /**
     * IMP2-2.7 (#1483) — abort the run once blocking errors exceed this
     * percentage of processed rows. null = no threshold (partial behaviour).
     */
    #[Assert\Range(min: 0, max: 100)]
    public ?int $allowedErrorsPct = null;
}
