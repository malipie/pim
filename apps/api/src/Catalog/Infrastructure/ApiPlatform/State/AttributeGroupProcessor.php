<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\CreateAttributeGroup\CreateAttributeGroupCommand;
use App\Catalog\Application\Command\DeleteAttributeGroup\DeleteAttributeGroupCommand;
use App\Catalog\Application\Command\UpdateAttributeGroup\UpdateAttributeGroupCommand;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Infrastructure\ApiPlatform\Resource\AttributeGroupInput;
use App\Catalog\Infrastructure\ApiPlatform\Resource\AttributeGroupPatchInput;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * UI-08.5 (#260) — AP4 State Processor for AttributeGroup.
 *
 * Bridges the input DTOs into CQRS commands and unwraps Messenger's
 * `HandlerFailedException` so AP4 sees the original HTTP exception
 * (404, 409, 422) instead of a 500. Mirrors `ApiProfileProcessor`
 * (#91) and `CatalogObjectProcessor` (#41).
 *
 * @implements ProcessorInterface<AttributeGroupInput|AttributeGroupPatchInput|AttributeGroup, AttributeGroup|null>
 */
final readonly class AttributeGroupProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private AttributeGroupRepositoryInterface $groups,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AttributeGroup
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
            'AttributeGroupProcessor cannot handle operation "%s".',
            $operation::class,
        ));
    }

    private function handlePost(mixed $data): AttributeGroup
    {
        if (!$data instanceof AttributeGroupInput) {
            throw new LogicException('AttributeGroupProcessor expects AttributeGroupInput on Post.');
        }

        $command = new CreateAttributeGroupCommand(
            code: $data->code,
            label: $data->label,
            description: $data->description,
            icon: $data->icon,
            color: $data->color,
            position: $data->position,
            requiredSection: $data->requiredSection,
            shared: $data->shared,
            conditionalVisibility: $data->conditionalVisibility,
        );

        $envelope = $this->dispatch($command);
        $newId = $this->extractResult($envelope);
        if (!$newId instanceof Uuid) {
            throw new LogicException('CreateAttributeGroupHandler returned an unexpected result.');
        }

        $created = $this->groups->findById($newId);
        if (null === $created) {
            throw new LogicException('Newly created AttributeGroup could not be re-loaded.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): AttributeGroup
    {
        if (!$data instanceof AttributeGroupPatchInput) {
            throw new LogicException('AttributeGroupProcessor expects AttributeGroupPatchInput on Patch.');
        }

        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->groups->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $id->toRfc4122(),
            ));
        }

        $command = new UpdateAttributeGroupCommand(
            id: $id,
            label: $data->label,
            description: $data->description,
            icon: $data->icon,
            color: $data->color,
            position: $data->position,
            requiredSection: $data->requiredSection,
            shared: $data->shared,
            conditionalVisibility: $data->conditionalVisibility,
        );
        $this->dispatch($command);

        $reloaded = $this->groups->findById($id);
        if (null === $reloaded) {
            throw new LogicException('Updated AttributeGroup could not be re-loaded.');
        }

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $id = $this->idFromUriVariables($uriVariables);
        $this->dispatch(new DeleteAttributeGroupCommand($id));

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
            throw new LogicException('AttributeGroupProcessor requires {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }

    private function extractResult(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
