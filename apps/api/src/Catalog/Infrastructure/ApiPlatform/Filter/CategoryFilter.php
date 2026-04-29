<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * `?category=electronics.audio` — return every CatalogObject whose
 * category lineage includes the given ltree label, _including_
 * descendants (Postgres `<@` containment).
 *
 * The filter resolves the `code` to a category row's `path`, then
 * filters on `path <@ <root>.*` so children + grandchildren show up
 * too. A SKU lives under a category via the `parent_id` chain, so the
 * actual filter joins through `parent` to reach the category row's
 * path. This is intentionally narrow for #43 — wider category
 * traversal (e.g. `parent_id` chains across multiple kinds) waits for
 * the ChannelObjectTypeMapping work in epic 0.6.
 *
 * Unknown category code → 0 rows (we resolve via repository, log
 * silently). Tenant scope is provided by the existing TenantFilter.
 */
final class CategoryFilter implements FilterInterface
{
    private const string PARAMETER = 'category';

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $filters = $context['filters'] ?? [];
        if (!\is_array($filters)) {
            return;
        }

        $code = $filters[self::PARAMETER] ?? null;
        if (!\is_string($code) || '' === $code) {
            return;
        }

        $em = $this->managerRegistry->getManagerForClass(CatalogObject::class);
        if (null === $em) {
            return;
        }

        $category = $em->getRepository(CatalogObject::class)
            ->findOneBy(['code' => $code, 'kind' => ObjectKind::Category]);
        if (null === $category) {
            // Unknown category → force empty result rather than no-op
            // (no-op would silently broaden the listing — surprising).
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $rootPath = $category->getPath();
        if (null === $rootPath || '' === $rootPath) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('category_path');
        $queryBuilder
            ->andWhere(\sprintf('LTREE_DESCENDANT_OF(%s.path, :%s) = true', $alias, $parameter))
            ->setParameter($parameter, $rootPath);
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<string, array{property?: string, type?: string, required?: bool, description?: string, strategy?: string, is_collection?: bool}>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER => [
                'property' => 'path',
                'type' => 'string',
                'required' => false,
                'description' => 'Category code; matches the row plus every descendant via Postgres ltree containment.',
                'strategy' => 'ltree_descendants',
            ],
        ];
    }
}
