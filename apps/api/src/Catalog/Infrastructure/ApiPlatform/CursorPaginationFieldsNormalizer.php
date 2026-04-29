<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Normalises `paginationViaCursor` from the AP4 XML extractor's
 * assoc-array format (`['id' => 'DESC']`) into the list-of-dicts
 * format the Hydra normalizer iterates as `[['field' => 'id',
 * 'direction' => 'DESC']]`.
 *
 * AP4 4.x's `XmlResourceExtractor::buildPaginationViaCursor` produces
 * the wrong shape — `array<string,string>` keyed by field — while
 * `PartialCollectionViewNormalizer::cursorPaginationFields()` does
 * `$field['field']` and `$field['direction']` over each entry, which
 * blows up with "cannot access offset of type string on string".
 *
 * The decorator slots between the cached and identifier factories so
 * every cached lookup gets the canonical shape; cache cost is one-time.
 */
final readonly class CursorPaginationFieldsNormalizer implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $resourceIndex => $resource) {
            $operations = $resource->getOperations();
            if (null === $operations) {
                continue;
            }

            $rebuilt = false;
            $newOperations = $operations;
            foreach ($operations as $operationName => $operation) {
                /** @var HttpOperation $operation — Operations collection narrows to HttpOperation per AP4 4.x typings */
                $cursor = $operation->getPaginationViaCursor();
                if (null === $cursor || [] === $cursor) {
                    continue;
                }

                $normalised = $this->normalise($cursor);
                if ($normalised === $cursor) {
                    continue;
                }

                $newOperations = $newOperations->add($operationName, $operation->withPaginationViaCursor($normalised));
                $rebuilt = true;
            }

            if ($rebuilt) {
                $collection[$resourceIndex] = $resource->withOperations($newOperations);
            }
        }

        return $collection;
    }

    /**
     * @param array<int|string, mixed> $cursor
     *
     * @return list<array{field: string, direction: string}>
     */
    private function normalise(array $cursor): array
    {
        $result = [];
        foreach ($cursor as $key => $value) {
            // Already in canonical list shape: `[['field' => 'x', 'direction' => 'DESC']]`.
            if (\is_int($key) && \is_array($value) && isset($value['field'], $value['direction'])) {
                $field = $value['field'];
                $direction = $value['direction'];
                if (\is_string($field) && \is_string($direction)) {
                    $result[] = ['field' => $field, 'direction' => $direction];
                }
                continue;
            }

            // XmlResourceExtractor's assoc shape: `['id' => 'DESC']`.
            if (\is_string($key) && \is_string($value)) {
                $result[] = ['field' => $key, 'direction' => $value];
            }
        }

        return $result;
    }
}
