<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

final class ImportScheduleInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $cron = '';

    #[Assert\Choice(choices: ['high', 'normal', 'low'])]
    public string $priority = 'normal';

    public bool $enabled = true;

    #[Assert\Uuid]
    public ?string $sourceId = null;

    #[Assert\Uuid]
    public ?string $profileId = null;

    /**
     * @var list<string>
     */
    public array $notifyChannels = [];

    /**
     * @var array<string, mixed>
     */
    public array $notifyConfig = [];
}
