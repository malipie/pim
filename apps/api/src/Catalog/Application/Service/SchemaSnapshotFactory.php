<?php

declare(strict_types=1);

namespace App\Catalog\Application\Service;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use DateTimeImmutable;

use const DATE_ATOM;

/**
 * CHC-04 (#1288) — builds the `schema_snapshot` payload (effective
 * attribute-group ids + capture time + master category) for a product.
 * Shared by the first-fill listener and the acknowledge endpoint so both
 * produce the identical shape the drift handler compares against.
 */
final readonly class SchemaSnapshotFactory
{
    public function __construct(
        private EffectiveAttributeGroupResolver $resolver,
        private ObjectCategoryRepositoryInterface $categories,
    ) {
    }

    /**
     * @return array{attributeGroupIds: list<string>, capturedAt: string, masterCategoryId: string|null}
     */
    public function build(CatalogObject $object): array
    {
        $groupIds = array_map(
            static fn (AttributeGroup $group): string => $group->getId()->toRfc4122(),
            $this->resolver->resolve($object),
        );

        $primary = $this->categories->findPrimary($object);

        return [
            'attributeGroupIds' => $groupIds,
            'capturedAt' => new DateTimeImmutable()->format(DATE_ATOM),
            'masterCategoryId' => $primary?->getCategory()->getId()->toRfc4122(),
        ];
    }
}
