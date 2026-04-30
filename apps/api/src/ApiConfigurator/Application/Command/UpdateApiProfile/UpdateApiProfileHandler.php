<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\UpdateApiProfile;

use App\ApiConfigurator\Application\WebhookSecretGenerator;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateApiProfileHandler
{
    public function __construct(
        private ApiProfileRepositoryInterface $profiles,
        private WebhookSecretGenerator $secrets,
    ) {
    }

    public function __invoke(UpdateApiProfileCommand $command): void
    {
        $profile = $this->profiles->findById($command->id);
        if (null === $profile) {
            throw new NotFoundHttpException(\sprintf(
                'ApiProfile "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        if (null !== $command->name) {
            $profile->rename($command->name);
        }
        if (null !== $command->description) {
            $profile->setDescription($command->description);
        }
        if (null !== $command->outputFormat) {
            $profile->setOutputFormat($command->outputFormat);
        }
        if (null !== $command->objectTypeIds) {
            $profile->setObjectTypeIds($command->objectTypeIds);
        }
        if (null !== $command->includedAttributes) {
            $profile->setIncludedAttributes($command->includedAttributes);
        }
        if (null !== $command->filters) {
            $profile->setFilters($command->filters);
        }
        if (null !== $command->webhookUrl) {
            $profile->setWebhookUrl($command->webhookUrl);
            // Mint the HMAC secret on first URL configuration so the
            // admin does not need a separate "rotate" call to enable
            // delivery. Subsequent URL changes keep the existing
            // secret — operators rotate explicitly via the controller.
            if ('' !== $command->webhookUrl && null === $profile->getWebhookSecret()) {
                $profile->setWebhookSecret($this->secrets->generate());
            }
        }
        if (null !== $command->webhookEvents) {
            $profile->setWebhookEvents($command->webhookEvents);
        }
        if (null !== $command->rateLimitPerHour) {
            $profile->setRateLimitPerHour($command->rateLimitPerHour);
        }

        $this->profiles->save($profile);
    }
}
