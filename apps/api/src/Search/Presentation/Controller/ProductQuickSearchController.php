<?php

declare(strict_types=1);

namespace App\Search\Presentation\Controller;

use App\Catalog\Domain\ObjectKind;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Search\Application\CatalogSearchService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * UI-02.2 (#292) — products quick search for the products list toolbar.
 *
 * Strict-mode wrapper over {@see CatalogSearchService}: forces
 * `matchingStrategy=all`, narrows the search surface to `sku` + `name`,
 * caps to 50 hits, and exposes a flat `{hits, total, processingTimeMs}`
 * shape that `<ProductSearchBar>` (UI-02.9) consumes verbatim.
 *
 * The underlying service already short-circuits to an empty result when
 * Meilisearch is unreachable (try/catch + warning log) — the products
 * list `<EmptyState>` swallows that as a no-match render. A Postgres
 * `LIKE 'q%'` fallback path is deferred until we observe a real outage
 * pattern (Faza 1+ candidate, see UI-02.2 ticket out-of-scope notes).
 *
 * Tenant gate: the wrapped service stamps a `tenantId = "<uuid>"`
 * filter from `CurrentTenantProvider` — cross-tenant leak is impossible
 * even when the caller forgets to pass a filter.
 */
final class ProductQuickSearchController
{
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private readonly CatalogSearchService $searchService,
    ) {
    }

    /**
     * Priority `200` lifts this above the API Platform `/api/products/{id}`
     * collection routes (default priority `0`); without it the literal
     * segment `quick-search` would be parsed as `id` and 404 with
     * "Invalid uri variables".
     */
    #[Route('/api/products/quick-search', name: 'pim_products_quick_search', methods: ['GET'], priority: 200)]
    #[IsGranted('ROLE_USER')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q', ''));

        $rawLimit = $request->query->getString('limit', '');
        $limit = '' === $rawLimit
            ? self::DEFAULT_LIMIT
            : max(1, min(self::MAX_LIMIT, (int) $rawLimit));

        if ('' === $query) {
            return new JsonResponse([
                'hits' => [],
                'total' => 0,
                'processingTimeMs' => 0,
            ]);
        }

        $result = $this->searchService->search(
            kind: ObjectKind::Product,
            query: $query,
            page: 1,
            perPage: $limit,
            extra: [
                'matchingStrategy' => 'all',
                'attributesToSearchOn' => ['sku', 'name', 'code'],
            ],
        );

        return new JsonResponse([
            'hits' => $result['hits'],
            'total' => $result['totalHits'],
            'processingTimeMs' => $result['processingTimeMs'],
        ]);
    }
}
