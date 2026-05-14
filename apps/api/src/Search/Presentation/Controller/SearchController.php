<?php

declare(strict_types=1);

namespace App\Search\Presentation\Controller;

use App\Catalog\Application\Filter\FilterDslResolver;
use App\Catalog\Application\Filter\FilterUrlSerializer;
use App\Catalog\Domain\Entity\SmartFilterPreset;
use App\Catalog\Domain\ObjectKind;
use App\Search\Application\CatalogSearchService;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

use const FILTER_VALIDATE_BOOLEAN;

/**
 * `/api/{kind}/search` endpoints (#52 / 0.5.4).
 *
 * Three sugar paths mirror the catalog routing: `/api/products/search`,
 * `/api/categories/search`, `/api/assets/search`. Each delegates to
 * {@see CatalogSearchService} with the matching ObjectKind.
 *
 * Response shape (JSON, not JSON-LD): `{hits, totalHits,
 * facetDistribution, processingTimeMs, page, perPage}`. Search is a
 * front-end ergonomic surface — the canonical resource representation
 * lives behind `/api/products/{id}` etc.
 *
 * Authorization: `is_granted('ROLE_USER')` — voter-level checks
 * (object.read) are layered on the resource read paths; search
 * does not surface row-level data beyond what the indexer pushed,
 * and tenant scoping in the service guards cross-tenant leakage.
 */
final class SearchController
{
    public function __construct(
        private readonly CatalogSearchService $searchService,
        private readonly FilterDslResolver $filterDslResolver,
        private readonly FilterUrlSerializer $filterUrlSerializer,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route('/api/search/products', name: 'pim_search_products', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function products(Request $request): JsonResponse
    {
        return $this->run($request, ObjectKind::Product);
    }

    #[Route('/api/search/categories', name: 'pim_search_categories', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function categories(Request $request): JsonResponse
    {
        return $this->run($request, ObjectKind::Category);
    }

    #[Route('/api/search/assets', name: 'pim_search_assets', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function assets(Request $request): JsonResponse
    {
        return $this->run($request, ObjectKind::Asset);
    }

    private function run(Request $request, ObjectKind $kind): JsonResponse
    {
        // VIEW-20 (#551) — text search lives under `?query=` so it does not
        // collide with `?q=<base64-blob>` introduced by VIEW-10 for the
        // filter DSL URL serializer. Falling back to `?q=` is kept for
        // backwards compatibility ONLY when the value clearly isn't a
        // base64 blob (no padding, short, contains spaces/non-base64 chars).
        $query = (string) ($request->query->get('query') ?? '');

        $filters = [];
        $rangeFilters = [];
        /** @var array<string, mixed> $rawFilters */
        $rawFilters = $request->query->all('filter');
        foreach ($rawFilters as $key => $value) {
            if (\is_array($value)) {
                // Range syntax: filter[key][gte]=50 / filter[key][lte]=90 (UI-02.24).
                $isAssoc = array_keys($value) !== range(0, \count($value) - 1);
                if ($isAssoc && (isset($value['gte']) || isset($value['lte']))) {
                    $range = [];
                    if (isset($value['gte']) && is_numeric($value['gte'])) {
                        $range['gte'] = (float) $value['gte'];
                    }
                    if (isset($value['lte']) && is_numeric($value['lte'])) {
                        $range['lte'] = (float) $value['lte'];
                    }
                    if ([] !== $range) {
                        $rangeFilters[$key] = $range;
                    }
                    continue;
                }
                /** @var list<scalar> $coerced */
                $coerced = array_values(array_filter($value, 'is_scalar'));
                $filters[$key] = $coerced;
                continue;
            }
            if (\is_scalar($value)) {
                $filters[$key] = $value;
            }
        }

        $facetsParam = $request->query->get('facets', '');
        $facets = '' === $facetsParam ? [] : array_values(array_filter(
            array_map('trim', explode(',', $facetsParam)),
            static fn (string $s): bool => '' !== $s,
        ));

        $page = max(1, (int) ($request->query->get('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->query->get('perPage') ?? 30)));
        $highlight = filter_var($request->query->get('highlight'), FILTER_VALIDATE_BOOLEAN);
        // VIEW-11 (#542) — count-only shortcut for cross-page selection
        // toolbar. Skips hit hydration + facet aggregation; Meilisearch
        // still has to evaluate the filter, but the payload is ~5x cheaper.
        $countOnly = filter_var($request->query->get('count_only'), FILTER_VALIDATE_BOOLEAN);
        if ($countOnly) {
            $perPage = 1;
            $facets = [];
        }

        // VIEW-10 (#538) — `smart_preset` + `filter` query params compile
        // a FilterDsl through the resolver and AND-merge the resulting
        // Meilisearch expression with the existing flat filters above.
        $customFilterExpression = $this->resolveCustomFilter($request);

        $result = $this->searchService->search(
            kind: $kind,
            query: $query,
            filters: $filters,
            facets: $facets,
            page: $page,
            perPage: $perPage,
            highlight: $highlight,
            rangeFilters: $rangeFilters,
            customFilterExpression: $customFilterExpression,
        );

        if ($countOnly) {
            return new JsonResponse([
                'totalHits' => $result['totalHits'],
                'processingTimeMs' => $result['processingTimeMs'],
            ]);
        }

        return new JsonResponse([
            'hits' => $result['hits'],
            'totalHits' => $result['totalHits'],
            'facetDistribution' => $result['facetDistribution'],
            'processingTimeMs' => $result['processingTimeMs'],
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Resolve `?smart_preset` and `?filter` query params into a
     * Meilisearch filter expression string, or `null` if neither is
     * present.
     */
    private function resolveCustomFilter(Request $request): ?string
    {
        $smartPreset = $request->query->get('smart_preset');
        if (\is_string($smartPreset) && '' !== trim($smartPreset)) {
            $preset = $this->loadPreset(trim($smartPreset));
            if (null === $preset) {
                throw new NotFoundHttpException(\sprintf('Smart filter preset "%s" not found.', $smartPreset));
            }

            return $this->filterDslResolver->toMeilisearchFilter($preset->getQuery());
        }

        $blob = $request->query->get('q');
        if (\is_string($blob) && '' !== trim($blob)) {
            // VIEW-20 (#551) — `?q=` is now blob-only, but older FE versions
            // (pre-VIEW-20) may still send raw text here. If decoding fails
            // we silently treat the param as text-search and skip the filter
            // path rather than 400-ing the whole request.
            try {
                $dsl = $this->filterUrlSerializer->fromBase64(trim($blob));
            } catch (BadRequestHttpException) {
                return null;
            }
            if ([] === $dsl) {
                return null;
            }

            return $this->filterDslResolver->toMeilisearchFilter($dsl);
        }

        return null;
    }

    private function loadPreset(string $idOrSlug): ?SmartFilterPreset
    {
        $repo = $this->em->getRepository(SmartFilterPreset::class);
        if (1 === preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $idOrSlug)) {
            $preset = $repo->find(Uuid::fromString($idOrSlug));
            if ($preset instanceof SmartFilterPreset) {
                return $preset;
            }
        }

        $tenant = $this->tenantContext->get();
        // Try system-shipped (tenant=null) first, then tenant-owned.
        $bySlug = $repo->findBy(['slug' => $idOrSlug]);
        foreach ($bySlug as $candidate) {
            $candidateTenant = $candidate->getTenant();
            if (null === $candidateTenant) {
                return $candidate;
            }
            if (null !== $tenant && $candidateTenant->getId()->equals($tenant->getId())) {
                return $candidate;
            }
        }

        return null;
    }
}
