<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Channel\Application\Command\PatchChannelObjectTypeMapping\PatchChannelObjectTypeMappingCommand;
use App\Channel\Domain\Entity\ChannelObjectTypeMapping;
use App\Channel\Domain\Repository\ChannelObjectTypeMappingRepositoryInterface;
use App\Channel\Infrastructure\ApiPlatform\Resource\ChannelObjectTypeMappingPatchInput;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-06 (#418) — AP4 State Processor for ChannelObjectTypeMapping Patch.
 *
 * @implements ProcessorInterface<ChannelObjectTypeMappingPatchInput|ChannelObjectTypeMapping, ChannelObjectTypeMapping>
 */
final readonly class ChannelObjectTypeMappingProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ChannelObjectTypeMappingRepositoryInterface $mappings,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChannelObjectTypeMapping
    {
        if (!$operation instanceof Patch) {
            throw new LogicException(\sprintf(
                'ChannelObjectTypeMappingProcessor only handles Patch, got "%s".',
                $operation::class,
            ));
        }

        if (!$data instanceof ChannelObjectTypeMappingPatchInput) {
            throw new LogicException('ChannelObjectTypeMappingProcessor expects ChannelObjectTypeMappingPatchInput on Patch.');
        }

        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->mappings->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf(
                'ChannelObjectTypeMapping "%s" was not found.',
                $id->toRfc4122(),
            ));
        }

        $command = new PatchChannelObjectTypeMappingCommand(
            id: $id,
            targetField: $data->targetField,
        );
        $this->dispatch($command);

        $reloaded = $this->mappings->findById($id);
        if (null === $reloaded) {
            throw new LogicException('Updated ChannelObjectTypeMapping could not be re-loaded.');
        }

        return $reloaded;
    }

    private function dispatch(object $message): Envelope
    {
        try {
            return $this->bus->dispatch($message);
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof HttpException) {
                throw $previous;
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function idFromUriVariables(array $uriVariables): Uuid
    {
        $raw = $uriVariables['id'] ?? null;
        if ($raw instanceof Uuid) {
            return $raw;
        }
        if (!\is_string($raw) || '' === $raw) {
            throw new LogicException('ChannelObjectTypeMappingProcessor requires {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }
}
