<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\ApiPlatform\Resource;

use App\ApiConfigurator\Domain\Enum\OutputFormat;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input shape for `/api/api_profiles`. AP4 default Doctrine
 * processor cannot hydrate the setter-less aggregate directly — this
 * DTO is the deserialisation target.
 */
final class ApiProfileInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z0-9_-]+$/')]
    #[Groups(['api_profile:create'])]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    #[Groups(['api_profile:create'])]
    public string $name = '';

    #[Assert\Length(max: 4096)]
    #[Groups(['api_profile:create'])]
    public ?string $description = null;

    #[Assert\Choice(choices: ['json_ld', 'json'])]
    #[Groups(['api_profile:create'])]
    public string $outputFormat = OutputFormat::JSON_LD->value;

    /**
     * @var list<string>
     */
    #[Groups(['api_profile:create'])]
    public array $objectTypeIds = [];

    /**
     * @var list<string>
     */
    #[Groups(['api_profile:create'])]
    public array $includedAttributes = [];

    /**
     * @var array<string, mixed>
     */
    #[Groups(['api_profile:create'])]
    public array $filters = [];

    #[Assert\Length(max: 2048)]
    #[Groups(['api_profile:create'])]
    public ?string $webhookUrl = null;

    /**
     * @var list<string>
     */
    #[Groups(['api_profile:create'])]
    public array $webhookEvents = [];

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100000)]
    #[Groups(['api_profile:create'])]
    public int $rateLimitPerHour = 1000;
}
