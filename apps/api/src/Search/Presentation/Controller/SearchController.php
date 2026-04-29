<?php

declare(strict_types=1);

namespace App\Search\Presentation\Controller;

use App\Catalog\Domain\ObjectKind;
use App\Search\Application\CatalogSearchService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        $query = $request->query->get('q', '');

        $filters = [];
        /** @var array<string, mixed> $rawFilters */
        $rawFilters = $request->query->all('filter');
        foreach ($rawFilters as $key => $value) {
            if (\is_array($value)) {
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

        $result = $this->searchService->search(
            kind: $kind,
            query: $query,
            filters: $filters,
            facets: $facets,
            page: $page,
            perPage: $perPage,
            highlight: $highlight,
        );

        return new JsonResponse([
            'hits' => $result['hits'],
            'totalHits' => $result['totalHits'],
            'facetDistribution' => $result['facetDistribution'],
            'processingTimeMs' => $result['processingTimeMs'],
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }
}
