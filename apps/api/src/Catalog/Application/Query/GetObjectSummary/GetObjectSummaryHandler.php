<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectSummary;

use App\Catalog\Contracts\Query\ObjectSummary;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use LogicException;

/**
 * Resolves a {@see GetObjectSummaryQuery} to {@see ObjectSummary}, or
 * returns null when the row is missing. Plain service — invoked
 * synchronously by cross-BC validators (e.g. ChannelCategoryRootValidator
 * after RF-19), no Messenger envelope needed.
 */
final readonly class GetObjectSummaryHandler
{
    public function __construct(
        private CatalogObjectRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetObjectSummaryQuery $query): ?ObjectSummary
    {
        $object = $this->repository->findById($query->objectId);
        if (null === $object) {
            return null;
        }

        $tenant = $object->getTenant();
        if (null === $tenant) {
            // TenantAssignmentListener stamps tenant on prePersist; a hydrated
            // row without tenant means the row is mid-flight (unsaved) which
            // a read-side query should never see.
            throw new LogicException('CatalogObject hydrated without a tenant — corrupt fixture or persistence bug.');
        }

        $labelAttributeCode = $object->getObjectType()->getLabelAttribute()?->getCode();
        $indexed = $object->getAttributesIndexed();
        $label = [];
        if (null !== $labelAttributeCode && isset($indexed[$labelAttributeCode]) && \is_array($indexed[$labelAttributeCode])) {
            /** @var array<string, string> $rawLabel */
            $rawLabel = array_filter(
                $indexed[$labelAttributeCode],
                static fn ($v): bool => \is_string($v),
            );
            $label = $rawLabel;
        }

        return new ObjectSummary(
            id: $object->getId(),
            kind: $object->getKind(),
            code: $object->getCode(),
            label: $label,
            tenantId: $tenant->getId(),
            parentId: $object->getParent()?->getId(),
        );
    }
}
