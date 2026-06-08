<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Channel\Application\Command\CreateChannel\CreateChannelCommand;
use App\Channel\Application\Command\DeleteChannel\DeleteChannelCommand;
use App\Channel\Application\Command\UpdateChannel\UpdateChannelCommand;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Channel\Infrastructure\ApiPlatform\Resource\ChannelInput;
use App\Channel\Infrastructure\ApiPlatform\Resource\ChannelPatchInput;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-06 (#418) — AP4 State Processor for Channel write operations.
 * Mirrors AttributeProcessor: bridges DTOs into CQRS commands and unwraps
 * Messenger HandlerFailedException so AP4 surfaces the original
 * 404/409/422 instead of a generic 500.
 *
 * @implements ProcessorInterface<ChannelInput|ChannelPatchInput|Channel, Channel|null>
 */
final readonly class ChannelProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ChannelRepositoryInterface $channels,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Channel
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables);
        }

        if ($operation instanceof Post) {
            return $this->handlePost($data);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        throw new LogicException(\sprintf(
            'ChannelProcessor cannot handle operation "%s".',
            $operation::class,
        ));
    }

    private function handlePost(mixed $data): Channel
    {
        if (!$data instanceof ChannelInput) {
            throw new LogicException('ChannelProcessor expects ChannelInput on Post.');
        }

        $command = new CreateChannelCommand(
            code: $data->code,
            name: $data->name,
            categoryTreeRootId: null,
        );

        $envelope = $this->dispatch($command);
        $newId = $this->extractResult($envelope);
        if (!$newId instanceof Uuid) {
            throw new LogicException('CreateChannelHandler returned an unexpected result.');
        }

        $created = $this->channels->findById($newId);
        if (null === $created) {
            throw new LogicException('Newly created Channel could not be re-loaded.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): Channel
    {
        if (!$data instanceof ChannelPatchInput) {
            throw new LogicException('ChannelProcessor expects ChannelPatchInput on Patch.');
        }

        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->channels->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf(
                'Channel "%s" was not found.',
                $id->toRfc4122(),
            ));
        }

        // The channel navigation root is managed through the navigation-tree
        // endpoints (CHC-01, #1284), not channel PATCH — always unchanged here.
        $command = new UpdateChannelCommand(
            id: $id,
            name: $data->name,
            categoryTreeRootId: false,
        );
        $this->dispatch($command);

        $reloaded = $this->channels->findById($id);
        if (null === $reloaded) {
            throw new LogicException('Updated Channel could not be re-loaded.');
        }

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $id = $this->idFromUriVariables($uriVariables);
        $this->dispatch(new DeleteChannelCommand($id));

        return null;
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
            throw new LogicException('ChannelProcessor requires {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }

    private function extractResult(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
