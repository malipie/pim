<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\CreateApiProfile;

use App\ApiConfigurator\Domain\Enum\OutputFormat;

final readonly class CreateApiProfileCommand
{
    /**
     * @param list<string>         $objectTypeIds
     * @param list<string>         $includedAttributes
     * @param array<string, mixed> $filters
     * @param list<string>         $webhookEvents
     */
    public function __construct(
        public string $code,
        public string $name,
        public OutputFormat $outputFormat,
        public ?string $description = null,
        public array $objectTypeIds = [],
        public array $includedAttributes = [],
        public array $filters = [],
        public ?string $webhookUrl = null,
        public array $webhookEvents = [],
        public int $rateLimitPerHour = 1000,
    ) {
    }
}
