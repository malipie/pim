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
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CatalogObjectInput;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CatalogObjectPatchInput;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private ObjectTypeRepositoryInterface $objectTypes,
        private RequestStack $requestStack,
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

        // ADR-015 — scope of the category tree this category joins.
        $categoryTargetObjectTypeId = null !== $data->categoryTargetObjectTypeId
            && '' !== $data->categoryTargetObjectTypeId
            ? Uuid::fromString($data->categoryTargetObjectTypeId)
            : null;

        $command = new CreateCatalogObjectCommand(
            objectTypeId: Uuid::fromString($data->objectTypeId),
            code: $data->code,
            expectedKind: $this->expectedKindFor($operation, $data),
            parentId: null !== $data->parentId ? Uuid::fromString($data->parentId) : null,
            attributes: $data->attributes ?? [],
            categoryIds: $categoryIds,
            primaryCategoryId: $primaryCategoryId,
            categoryTargetObjectTypeId: $categoryTargetObjectTypeId,
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

        // #1148 — locale scope for the attribute write travels as a query
        // param (`?locale=en`), not a body field: JSON Merge Patch cannot
        // tell an absent locale from an explicit null, and a body key would
        // collide with the attribute payload.
        $request = $this->requestStack->getCurrentRequest();
        $localeParam = $request?->query->get('locale');
        $channelParam = $request?->query->get('channel');
        $locale = \is_string($localeParam) && '' !== $localeParam ? $localeParam : null;
        $channel = \is_string($channelParam) && '' !== $channelParam ? $channelParam : null;

        $command = new UpdateCatalogObjectCommand(
            id: $id,
            enabled: $data->enabled,
            status: $data->status,
            parentId: null !== $data->parentId ? Uuid::fromString($data->parentId) : null,
            clearParent: false,
            path: $data->path,
            clearPath: false,
            attributes: $data->attributes,
            expectedVersion: $data->expectedVersion,
            locale: $locale,
            channel: $channel,
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

    /**
     * Per-kind sugar paths (`POST /api/products`, `/api/categories`) declare
     * their kind via `extraProperties.kind`; CreateCatalogObjectHandler then
     * 422s when the supplied `objectTypeId` resolves to a different kind, so
     * a request to `/api/products` with a category ObjectType is rejected.
     *
     * The poly-kind `POST /api/objects` (#981) intentionally omits the
     * discriminator — relation pickers create targets of arbitrary kinds
     * (built-in product/category/asset AND custom kinds) without knowing
     * the sugar path. For that path we derive the kind from the ObjectType
     * row itself; the equality guard in the handler then becomes a no-op
     * but the rest of its validation (tenant scope, existence) keeps
     * running unchanged.
     */
    private function expectedKindFor(Operation $operation, CatalogObjectInput $data): ObjectKind
    {
        $value = $operation->getExtraProperties()['kind'] ?? null;
        if (\is_string($value)) {
            $kind = ObjectKind::tryFrom($value);
            if (null === $kind) {
                throw new UnprocessableEntityHttpException(\sprintf('Unknown ObjectKind "%s".', $value));
            }

            return $kind;
        }

        try {
            $objectTypeId = Uuid::fromString($data->objectTypeId);
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is not a valid UUID.', $data->objectTypeId), $e);
        }

        $objectType = $this->objectTypes->findById($objectTypeId);
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $data->objectTypeId));
        }

        return $objectType->getKind();
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
