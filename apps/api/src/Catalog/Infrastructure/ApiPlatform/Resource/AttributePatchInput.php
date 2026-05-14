<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * VIEW-02 (#374) — PATCH input shape for `/api/attributes/{id}`. Every
 * field is nullable so we can distinguish "not provided" from "set to
 * empty"; the Update handler only applies fields that are non-null in
 * the patch payload.
 *
 * `code` and `type` are intentionally absent — code is immutable
 * post-create and type changes go through the dedicated migrate-type
 * flow (Sprint 0 endpoint, not editable through PATCH).
 */
final class AttributePatchInput
{
    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute:patch'])]
    public ?array $label = null;

    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute:patch'])]
    public ?array $help = null;

    #[Groups(['attribute:patch'])]
    public ?bool $localizable = null;

    #[Groups(['attribute:patch'])]
    public ?bool $scopable = null;

    #[Groups(['attribute:patch'])]
    public ?bool $required = null;

    /**
     * VIEW-38 (#579) — toggle from Settings → Attributes for whether
     * the attribute appears as a Meilisearch filterableAttribute.
     */
    #[Groups(['attribute:patch'])]
    public ?bool $filterable = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute:patch'])]
    public ?array $validationRules = null;

    #[Assert\PositiveOrZero]
    #[Groups(['attribute:patch'])]
    public ?int $position = null;
}
