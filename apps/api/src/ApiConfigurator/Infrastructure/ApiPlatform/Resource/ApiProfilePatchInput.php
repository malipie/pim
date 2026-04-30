<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH input shape for `/api/api_profiles/{id}`. Every field is
 * optional — only non-null members propagate to the update command.
 *
 * `code` is intentionally absent: profile codes are referenced by
 * `ApiKey.scopes` and changing one would silently invalidate live
 * keys. Operators rotate by deleting + recreating, not renaming.
 */
final class ApiProfilePatchInput
{
    #[Assert\Length(max: 128)]
    #[Groups(['api_profile:patch'])]
    public ?string $name = null;

    #[Assert\Length(max: 4096)]
    #[Groups(['api_profile:patch'])]
    public ?string $description = null;

    #[Assert\Choice(choices: ['json_ld', 'json'])]
    #[Groups(['api_profile:patch'])]
    public ?string $outputFormat = null;

    /**
     * @var list<string>|null
     */
    #[Groups(['api_profile:patch'])]
    public ?array $objectTypeIds = null;

    /**
     * @var list<string>|null
     */
    #[Groups(['api_profile:patch'])]
    public ?array $includedAttributes = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Groups(['api_profile:patch'])]
    public ?array $filters = null;

    #[Assert\Length(max: 2048)]
    #[Groups(['api_profile:patch'])]
    public ?string $webhookUrl = null;

    /**
     * @var list<string>|null
     */
    #[Groups(['api_profile:patch'])]
    public ?array $webhookEvents = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100000)]
    #[Groups(['api_profile:patch'])]
    public ?int $rateLimitPerHour = null;
}
