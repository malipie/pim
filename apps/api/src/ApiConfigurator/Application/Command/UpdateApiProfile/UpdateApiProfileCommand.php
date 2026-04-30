<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\UpdateApiProfile;

use App\ApiConfigurator\Domain\Enum\OutputFormat;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateApiProfileCommand
{
    /**
     * @param list<string>|null         $objectTypeIds
     * @param list<string>|null         $includedAttributes
     * @param array<string, mixed>|null $filters
     * @param list<string>|null         $webhookEvents
     */
    public function __construct(
        public Uuid $id,
        public ?string $name = null,
        public ?string $description = null,
        public ?OutputFormat $outputFormat = null,
        public ?array $objectTypeIds = null,
        public ?array $includedAttributes = null,
        public ?array $filters = null,
        public ?string $webhookUrl = null,
        public ?array $webhookEvents = null,
        public ?int $rateLimitPerHour = null,
    ) {
    }
}
