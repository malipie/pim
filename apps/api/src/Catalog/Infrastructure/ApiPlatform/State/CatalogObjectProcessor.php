<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\Command\CreateCatalogObject\CreateCatalogObjectCommand;
use App\Catalog\Application\Command\DeleteCatalogObject\DeleteCatalogObjectCommand;
use App\Catalog\Application\Command\UpdateCatalogObject\UpdateCatalogObjectCommand;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CatalogObjectInput;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CatalogObjectPatchInput;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Bridge between API Platform write operations and the CQRS command bus
 * for {@see CatalogObject}.
 *
 * Per ADR-0012, write paths in epic 0.4+ are dispatched as
 * `Application/Command/<UseCase>/{Command,Handler}` slices. The
 * processor reads the operation kind from `extraProperties.kind`
 * (populated by the per-sugar-path XML resource declarations), maps
 * the AP4 deserialised input to the matching command, dispatches via
 * `messenger.bus.default`, then re-fetches the aggregate for the
 * normaliser to render the response.
 *
 * Setter-less Domain entities are the reason the input cannot be a
 * `CatalogObject` directly — AP4's denormaliser would have nowhere to
 * write the values. The DTOs ({@see CatalogObjectInput},
 * {@see CatalogObjectPatchInput}) carry public fields that AP4 can
 * populate, the processor consumes them.
 *
 * @implements ProcessorInterface<CatalogObjectInput|CatalogObjectPatchInput|CatalogObject, CatalogObject|null>
 */
final readonly class CatalogObjectProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private CatalogObjectRepositoryInterface $catalogObjects,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CatalogObject
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables);
        }

        if ($operation instanceof Post) {
            return $this->handlePost($data, $operation);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        throw new LogicException(\sprintf(
            'CatalogObjectProcessor cannot handle operation "%s".',
            $operation::class,
        ));
    }

    private function handlePost(mixed $data, Operation $operation): CatalogObject
    {
        if (!$data instanceof CatalogObjectInput) {
            throw new LogicException('CatalogObjectProcessor expects CatalogObjectInput on Post.');
        }

        $categoryIds = null;
        if (null !== $data->categoryIds) {
            $categoryIds = [];
            foreach ($data->categoryIds as $rawId) {
                if ('' === $rawId) {
                    throw new UnprocessableEntityHttpException('Each entry of "categoryIds" must be a non-empty UUID string.');
                }
                try {
                    $categoryIds[] = Uuid::fromString($rawId);
                } catch (InvalidArgumentException $e) {
                    throw new UnprocessableEntityHttpException(\sprintf('"%s" is not a valid UUID.', $rawId), $e);
                }
            }
        }
        $primaryCategoryId = null !== $data->primaryCategoryId && '' !== $data->primaryCategoryId
            ? Uuid::fromString($data->primaryCategoryId)
            : null;

        $command = new CreateCatalogObjectCommand(
            objectTypeId: Uuid::fromString($data->objectTypeId),
            code: $data->code,
            expectedKind: $this->kindOrFail($operation),
            parentId: null !== $data->parentId ? Uuid::fromString($data->parentId) : null,
            attributes: $data->attributes ?? [],
            categoryIds: $categoryIds,
            primaryCategoryId: $primaryCategoryId,
        );

        $envelope = $this->dispatch($command);
        $newId = $this->extractResult($envelope);
        if (!$newId instanceof Uuid) {
            throw new LogicException('CreateCatalogObjectHandler returned an unexpected result.');
        }

        $created = $this->catalogObjects->findById($newId);
        if (null === $created) {
            throw new LogicException('Newly created CatalogObject could not be re-loaded.');
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): CatalogObject
    {
        if (!$data instanceof CatalogObjectPatchInput) {
            throw new LogicException('CatalogObjectProcessor expects CatalogObjectPatchInput on Patch.');
        }

        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->catalogObjects->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf('CatalogObject "%s" was not found.', $id->toRfc4122()));
        }

        $command = new UpdateCatalogObjectCommand(
            id: $id,
            enabled: $data->enabled,
            status: $data->status,
            parentId: null !== $data->parentId ? Uuid::fromString($data->parentId) : null,
            clearParent: false,
            path: $data->path,
            clearPath: false,
            attributes: $data->attributes,
        );
        $this->dispatch($command);

        $reloaded = $this->catalogObjects->findById($id);
        if (null === $reloaded) {
            throw new LogicException('Updated CatalogObject could not be re-loaded.');
        }

        return $reloaded;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $id = $this->idFromUriVariables($uriVariables);
        $existing = $this->catalogObjects->findById($id);
        if (null === $existing) {
            throw new NotFoundHttpException(\sprintf('CatalogObject "%s" was not found.', $id->toRfc4122()));
        }

        $this->dispatch(new DeleteCatalogObjectCommand($id));

        return null;
    }

    /**
     * Wrap `MessageBus::dispatch` and unwrap `HandlerFailedException` so
     * the originating Http exception (`UnprocessableEntityHttpException`,
     * `NotFoundHttpException`, etc.) bubbles up with its real status code.
     * Without this every handler-side error renders as 500.
     */
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

    private function kindOrFail(Operation $operation): ObjectKind
    {
        $value = $operation->getExtraProperties()['kind'] ?? null;
        if (!\is_string($value)) {
            throw new UnprocessableEntityHttpException('Operation is missing the kind discriminator.');
        }
        $kind = ObjectKind::tryFrom($value);
        if (null === $kind) {
            throw new UnprocessableEntityHttpException(\sprintf('Unknown ObjectKind "%s".', $value));
        }

        return $kind;
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
            throw new LogicException('CatalogObjectProcessor requires {id} URI variable.');
        }

        return Uuid::fromString($raw);
    }

    private function extractResult(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
