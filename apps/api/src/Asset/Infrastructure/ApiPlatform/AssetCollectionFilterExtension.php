<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

use const JSON_THROW_ON_ERROR;

/**
 * Collection extension for `GET /api/assets` (#438).
 *
 * Joins the assets table on `o.id = a.object_id` and applies the DAM
 * filters that live on storage-side columns:
 *   - `mimeGroup=image|pdf`     → mime_type prefix
 *   - `tag=<value>`             → JSONB containment on assets.tags
 *   - `search=<filename frag>`  → ILIKE on original_filename
 *   - `dateFrom=YYYY-MM-DD`     → created_at >= …
 *   - `dateTo=YYYY-MM-DD`       → created_at <= …  (inclusive end-of-day)
 *   - `sizeMin=<bytes>`/`sizeMax=<bytes>` → size range
 *
 * Standard catalog filters (cursor pagination, kind narrowing) keep
 * working through the existing `KindCollectionExtension` — this
 * extension only adds the asset-specific WHERE clauses for
 * `kind=asset` operations.
 *
 * Reads parameters from the active request rather than declaring
 * `ApiResource` filter classes per field — keeps the per-kind logic
 * in one file, leaving the shared `CatalogObject.xml` lean.
 */
final readonly class AssetCollectionFilterExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @param class-string $resourceClass
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        // CatalogObject FQCN as a literal string keeps Asset_Internals from
        // depending on Catalog_Internals (Deptrac ADR-0013). The mapping is
        // discovered through the AP4 resource class string at runtime, so
        // there is no compile-time benefit to importing the class.
        if ('App\\Catalog\\Domain\\Entity\\CatalogObject' !== $resourceClass) {
            return;
        }
        if ('asset' !== ($operation?->getExtraProperties()['kind'] ?? null)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        $assetAlias = 'asset_'.$queryNameGenerator->generateJoinAlias('asset');
        $queryBuilder->innerJoin(
            'App\\Asset\\Domain\\Entity\\Asset',
            $assetAlias,
            'WITH',
            \sprintf('%s.objectId = %s.id', $assetAlias, $alias),
        );

        $mimeGroup = $request->query->get('mimeGroup');
        if (\is_string($mimeGroup) && '' !== $mimeGroup) {
            $param = $queryNameGenerator->generateParameterName('mimeGroup');
            if ('image' === $mimeGroup) {
                $queryBuilder
                    ->andWhere(\sprintf('%s.mimeType LIKE :%s', $assetAlias, $param))
                    ->setParameter($param, 'image/%');
            } elseif ('pdf' === $mimeGroup) {
                $queryBuilder
                    ->andWhere(\sprintf('%s.mimeType = :%s', $assetAlias, $param))
                    ->setParameter($param, 'application/pdf');
            }
        }

        $search = $request->query->get('search');
        if (\is_string($search) && '' !== trim($search)) {
            $param = $queryNameGenerator->generateParameterName('search');
            $queryBuilder
                ->andWhere(\sprintf('LOWER(%s.originalFilename) LIKE :%s', $assetAlias, $param))
                ->setParameter($param, '%'.strtolower(trim($search)).'%');
        }

        $tag = $request->query->get('tag');
        if (\is_string($tag) && '' !== trim($tag)) {
            $param = $queryNameGenerator->generateParameterName('tag');
            $queryBuilder
                ->andWhere(\sprintf('JSONB_CONTAINS(%s.tags, :%s) = TRUE', $assetAlias, $param))
                ->setParameter($param, json_encode([trim($tag)], JSON_THROW_ON_ERROR));
        }

        $dateFrom = $request->query->get('dateFrom');
        if (\is_string($dateFrom) && '' !== $dateFrom) {
            $param = $queryNameGenerator->generateParameterName('dateFrom');
            $queryBuilder
                ->andWhere(\sprintf('%s.createdAt >= :%s', $assetAlias, $param))
                ->setParameter($param, new DateTimeImmutable($dateFrom));
        }
        $dateTo = $request->query->get('dateTo');
        if (\is_string($dateTo) && '' !== $dateTo) {
            $param = $queryNameGenerator->generateParameterName('dateTo');
            $queryBuilder
                ->andWhere(\sprintf('%s.createdAt <= :%s', $assetAlias, $param))
                ->setParameter($param, new DateTimeImmutable($dateTo.' 23:59:59'));
        }

        $sizeMin = $request->query->get('sizeMin');
        if (is_numeric($sizeMin)) {
            $param = $queryNameGenerator->generateParameterName('sizeMin');
            $queryBuilder
                ->andWhere(\sprintf('%s.size >= :%s', $assetAlias, $param))
                ->setParameter($param, (int) $sizeMin);
        }
        $sizeMax = $request->query->get('sizeMax');
        if (is_numeric($sizeMax)) {
            $param = $queryNameGenerator->generateParameterName('sizeMax');
            $queryBuilder
                ->andWhere(\sprintf('%s.size <= :%s', $assetAlias, $param))
                ->setParameter($param, (int) $sizeMax);
        }
    }
}
