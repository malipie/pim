<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH (RFC 7396 merge) input for `/api/connections/{id}` (APIC-P1-06). Every
 * field is nullable — null means "leave unchanged". `code` is immutable and
 * omitted. `status` accepts only the operator-controlled values
 * (active/paused); draft/error are set by the system. `credentials` rotates the
 * stored secret when present (empty map clears it).
 */
final class ConnectionPatchInput
{
    #[Assert\Length(max: 255)]
    #[Groups(['connection:patch'])]
    public ?string $name = null;

    #[Assert\Length(max: 2048)]
    #[Groups(['connection:patch'])]
    public ?string $baseUrl = null;

    #[Assert\Choice(choices: ['none', 'api_key', 'bearer', 'basic', 'oauth2_token'])]
    #[Groups(['connection:patch'])]
    public ?string $authType = null;

    #[Assert\Choice(choices: ['active', 'paused'])]
    #[Groups(['connection:patch'])]
    public ?string $status = null;

    /**
     * @var array<string, string>|null
     */
    #[Groups(['connection:patch'])]
    public ?array $credentials = null;

    /**
     * @var array<string, string>|null
     */
    #[Groups(['connection:patch'])]
    public ?array $defaultHeaders = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100000)]
    #[Groups(['connection:patch'])]
    public ?int $rateLimitHint = null;
}
