<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\CreateApiProfile;

use App\ApiConfigurator\Application\WebhookSecretGenerator;
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
        private WebhookSecretGenerator $secrets,
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

        // If the profile ships with a webhook URL, mint the HMAC secret
        // up front so the admin gets a usable webhook on first save —
        // avoids a "configure URL → rotate secret → save again" round
        // trip. Profiles without URL stay secretless until rotation.
        $webhookSecret = null !== $command->webhookUrl && '' !== $command->webhookUrl
            ? $this->secrets->generate()
            : null;

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
            webhookSecret: $webhookSecret,
            rateLimitPerHour: $command->rateLimitPerHour,
        );

        $this->profiles->save($profile);

        return $profile->getId();
    }
}
