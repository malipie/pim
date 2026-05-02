<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\CreateAttribute\CreateAttributeCommand;
use App\Catalog\Application\Command\DeleteAttribute\DeleteAttributeCommand;
use App\Catalog\Application\Command\UpdateAttribute\UpdateAttributeCommand;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Infrastructure\ApiPlatform\Resource\AttributeInput;
use App\Catalog\Infrastructure\ApiPlatform\Resource\AttributePatchInput;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-02 (#374) — AP4 State Processor for Attribute. Mirrors
 * AttributeGroupProcessor (#260): bridges DTOs into CQRS commands and
 * unwraps Messenger HandlerFailedException so AP4 surfaces the original
 * 404/409/422 instead of a generic 500.
 *
 * @implements ProcessorInterface<AttributeInput|AttributePatchInput|Attribute, Attribute|null>
 */
final readonly class AttributeProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private AttributeRepositoryInterface $attributes,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Attribute
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
            'AttributeProcessor cannot handle operation "%s".',
            $operation::class,
        ));
    }

    private function handlePost(mixed $data): Attribute
    {
        if (!$data instanceof AttributeInput) {
            throw new LogicException('AttributeProcessor expects AttributeInput on Post.');
        }

        $command = new CreateAttributeCommand(
            code: $data->code,
            label: $data->label,
            type: $data->type,
            help: $data->help,
            localizable: $data->localizable,
            scopable: $data->scopable,
            required: $data->required,
            validationRules: $data->validationRules,
            position: $data->position,
        );

        $envelope = $this->dispatch($command);
        $newId = $this->extractResult($envelope);
        if (!$newId instanceof Uuid) {
            throw new LogicException('CreateAttributeHandler returned an unexpected result.');
        }

        $created = $this->attributes->findById($newId);
        if (null === $created) {
            throw new LogicException('Newly created Attribute could not be re-loaded.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): Attribute
    {
        if (!$data instanceof AttributePatchInput) {
            throw new LogicException('AttributeProcessor expects AttributePatchInput on Patch.');
        }

        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->attributes->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf(
                'Attribute "%s" was not found.',
                $id->toRfc4122(),
            ));
        }

        $command = new UpdateAttributeCommand(
            id: $id,
            label: $data->label,
            help: $data->help,
            localizable: $data->localizable,
            scopable: $data->scopable,
            required: $data->required,
            validationRules: $data->validationRules,
            position: $data->position,
        );
        $this->dispatch($command);

        $reloaded = $this->attributes->findById($id);
        if (null === $reloaded) {
            throw new LogicException('Updated Attribute could not be re-loaded.');
        }

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $id = $this->idFromUriVariables($uriVariables);
        $this->dispatch(new DeleteAttributeCommand($id));

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
            throw new LogicException('AttributeProcessor requires {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }

    private function extractResult(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
