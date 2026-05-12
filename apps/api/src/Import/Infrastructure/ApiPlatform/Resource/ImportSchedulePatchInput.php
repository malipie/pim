<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

final class ImportSchedulePatchInput
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    public ?string $code = null;

    #[Assert\Length(max: 64)]
    public ?string $cron = null;

    #[Assert\Choice(choices: ['high', 'normal', 'low'])]
    public ?string $priority = null;

    public ?bool $enabled = null;

    #[Assert\Uuid]
    public ?string $sourceId = null;

    #[Assert\Uuid]
    public ?string $profileId = null;

    /**
     * @var list<string>|null
     */
    public ?array $notifyChannels = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $notifyConfig = null;
}
