<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input shape for `/api/connections` (APIC-P1-06). The setter-less,
 * tenant-stamped {@see \App\Integration\Generic\Domain\Entity\Connection} is
 * built by {@see \App\Integration\Generic\Infrastructure\ApiPlatform\State\ConnectionProcessor}.
 *
 * `credentials` is a write-only map (shape per authType) handed to the cipher;
 * it carries no read group, so it can never be serialised back out.
 */
final class ConnectionInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z0-9-]+$/')]
    #[Groups(['connection:create'])]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['connection:create'])]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    #[Groups(['connection:create'])]
    public string $baseUrl = '';

    #[Assert\Choice(choices: ['none', 'api_key', 'bearer', 'basic', 'oauth2_token'])]
    #[Groups(['connection:create'])]
    public string $authType = 'none';

    /**
     * @var array<string, string>
     */
    #[Groups(['connection:create'])]
    public array $credentials = [];

    /**
     * @var array<string, string>
     */
    #[Groups(['connection:create'])]
    public array $defaultHeaders = [];

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100000)]
    #[Groups(['connection:create'])]
    public ?int $rateLimitHint = null;
}
