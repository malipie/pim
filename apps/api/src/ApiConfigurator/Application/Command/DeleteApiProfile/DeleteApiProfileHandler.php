<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\DeleteApiProfile;

use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteApiProfileHandler
{
    public function __construct(
        private ApiProfileRepositoryInterface $profiles,
    ) {
    }

    public function __invoke(DeleteApiProfileCommand $command): void
    {
        $profile = $this->profiles->findById($command->id);
        if (null === $profile) {
            throw new NotFoundHttpException(\sprintf(
                'ApiProfile "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        $this->profiles->remove($profile);
    }
}
