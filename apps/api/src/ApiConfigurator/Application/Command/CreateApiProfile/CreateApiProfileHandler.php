<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\CreateApiProfile;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateApiProfileHandler
{
    public function __construct(
        private ApiProfileRepositoryInterface $profiles,
    ) {
    }

    public function __invoke(CreateApiProfileCommand $command): Uuid
    {
        if (null !== $this->profiles->findByCode($command->code)) {
            throw new ConflictHttpException(\sprintf(
                'ApiProfile with code "%s" already exists for this tenant.',
                $command->code,
            ));
        }

        $profile = new ApiProfile(
            code: $command->code,
            name: $command->name,
            outputFormat: $command->outputFormat,
            objectTypeIds: $command->objectTypeIds,
            includedAttributes: $command->includedAttributes,
            filters: $command->filters,
            description: $command->description,
            webhookUrl: $command->webhookUrl,
            webhookEvents: $command->webhookEvents,
            rateLimitPerHour: $command->rateLimitPerHour,
        );

        $this->profiles->save($profile);

        return $profile->getId();
    }
}
