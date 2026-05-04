<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\PatchChannelObjectTypeMapping;

use App\Channel\Domain\Repository\ChannelObjectTypeMappingRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchChannelObjectTypeMappingHandler
{
    public function __construct(
        private ChannelObjectTypeMappingRepositoryInterface $mappings,
    ) {
    }

    public function __invoke(PatchChannelObjectTypeMappingCommand $command): void
    {
        $mapping = $this->mappings->findById($command->id);
        if (null === $mapping) {
            throw new NotFoundHttpException(\sprintf(
                'ChannelObjectTypeMapping "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        if (null !== $command->targetField) {
            $mapping->mapToField($command->targetField);
        }

        $this->mappings->save($mapping);
    }
}
