<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Search\Infrastructure\MeilisearchClientFactory;
use App\Shared\Domain\Tenant;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Index a single `CatalogObject` into the matching Meilisearch index
 * (#50 / 0.5.2).
 *
 * One document per row, identified by the row's Uuid. Fields cover:
 *   - identity (`id`, `code`, `kind`)
 *   - editorial state (`status`, `enabled`)
 *   - parent / path for hierarchy lookups
 *   - timestamps for sortable lists
 *   - flat attributesIndexed snapshot (denormalised cache from #38)
 *
 * Tenant filtering is delegated to a `tenantId` filterable attribute —
 * read-side queries from `/api/products/search` (#52) inject the
 * authenticated user's tenant before hitting Meili.
 *
 * Hub failures are logged and swallowed (same fail-soft pattern as
 * MercurePublisher #47): search is a notification surface, not the
 * source of truth.
 */
final readonly class CatalogObjectIndexer
{
    private LoggerInterface $logger;

    public function __construct(
        private CatalogObjectRepositoryInterface $catalogObjects,
        private MeilisearchClientFactory $clientFactory,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function index(Uuid $objectId): void
    {
        $object = $this->catalogObjects->findById($objectId);
        if (null === $object) {
            $this->logger->warning('Indexer skipped — CatalogObject not found.', ['id' => $objectId->toRfc4122()]);

            return;
        }

        if (ObjectKind::Custom === $object->getKind()) {
            // Custom kinds have no MVP index per ADR-009; phase 2/3 unlock.
            return;
        }

        $this->push($object);
    }

    public function remove(Uuid $objectId, ObjectKind $kind): void
    {
        if (ObjectKind::Custom === $kind) {
            return;
        }

        try {
            $client = $this->clientFactory->create();
            $client->index(IndexSettingsTemplate::indexName($kind))->deleteDocument($objectId->toRfc4122());
        } catch (Throwable $e) {
            $this->logger->warning('Index delete failed: {message}', [
                'message' => $e->getMessage(),
                'id' => $objectId->toRfc4122(),
                'kind' => $kind->value,
            ]);
        }
    }

    private function push(CatalogObject $object): void
    {
        try {
            $client = $this->clientFactory->create();
            $client->index(IndexSettingsTemplate::indexName($object->getKind()))
                ->addDocuments([$this->toDocument($object)]);
        } catch (Throwable $e) {
            $this->logger->warning('Index push failed: {message}', [
                'message' => $e->getMessage(),
                'id' => $object->getId()->toRfc4122(),
                'kind' => $object->getKind()->value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toDocument(CatalogObject $object): array
    {
        $tenant = $object->getTenant();
        \assert($tenant instanceof Tenant);

        return [
            'id' => $object->getId()->toRfc4122(),
            'tenantId' => $tenant->getId()->toRfc4122(),
            'code' => $object->getCode(),
            'kind' => $object->getKind()->value,
            'objectTypeId' => $object->getObjectType()->getId()->toRfc4122(),
            'status' => $object->getStatus(),
            'enabled' => $object->isEnabled(),
            'parentId' => $object->getParent()?->getId()->toRfc4122(),
            'path' => $object->getPath(),
            'attributesIndexed' => $object->getAttributesIndexed(),
            'completeness' => $object->getCompleteness(),
            'createdAt' => $object->getCreatedAt()->getTimestamp(),
            'updatedAt' => $object->getUpdatedAt()->getTimestamp(),
        ];
    }
}
