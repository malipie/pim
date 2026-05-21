<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * PCAT-06 (#479) — `GET /api/categories/{id}/products`
 *
 * Reverse-listing of the product↔category junction (epic UI-10): given a
 * category, return the products assigned to it (paginated, hydra-shaped
 * so Refine can consume it via the standard data provider).
 *
 * Server-side filter joins `object_categories` on `object_id`; only
 * `kind=product` rows are returned (categories or assets cascading
 * through the same junction would be a domain bug, but we filter
 * defensively). TenantFilter on the parent fetch returns 404 for
 * cross-tenant categories before we touch the DBAL query.
 */
final class CategoryProductsController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
    private const int MAX_ITEMS_PER_PAGE = 200;
    private const int DEFAULT_ITEMS_PER_PAGE = 30;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        '/api/categories/{id}/products',
        name: 'pim_categories_products',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $category = $this->catalogObjects->findById(Uuid::fromString($id));
        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category "%s" was not found.', $id));
        }
        if (ObjectKind::Category !== $category->getKind()) {
            throw new UnprocessableEntityHttpException(\sprintf('Object "%s" is not a category.', $id));
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $itemsPerPage = (int) $request->query->get('itemsPerPage', (string) self::DEFAULT_ITEMS_PER_PAGE);
        if ($itemsPerPage < 1) {
            $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE;
        }
        if ($itemsPerPage > self::MAX_ITEMS_PER_PAGE) {
            $itemsPerPage = self::MAX_ITEMS_PER_PAGE;
        }
        $offset = ($page - 1) * $itemsPerPage;

        $thisId = $category->getId()->toRfc4122();

        $totalItems = self::toInt($this->connection->fetchOne(
            'SELECT COUNT(*) FROM object_categories oc'
            .' INNER JOIN objects o ON o.id = oc.object_id'
            ." WHERE oc.category_id = CAST(:id AS uuid) AND o.kind = 'product'",
            ['id' => $thisId],
        ));

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id AS id, o.code AS code, o.enabled AS enabled, o.status AS status,'
            .' o.attributes_indexed AS attributes_indexed,'
            .' oc.is_primary AS is_primary, oc.position AS position'
            .' FROM object_categories oc'
            .' INNER JOIN objects o ON o.id = oc.object_id'
            ." WHERE oc.category_id = CAST(:id AS uuid) AND o.kind = 'product'"
            .' ORDER BY o.id DESC'
            .' LIMIT :limit OFFSET :offset',
            ['id' => $thisId, 'limit' => $itemsPerPage, 'offset' => $offset],
        );

        $members = [];
        foreach ($rows as $row) {
            $rowId = self::asString($row['id'] ?? null);
            $rowCode = self::asString($row['code'] ?? null);
            $rowStatus = self::asString($row['status'] ?? null);
            $members[] = [
                '@id' => '/api/products/'.$rowId,
                '@type' => 'Product',
                'id' => $rowId,
                'code' => $rowCode,
                'enabled' => self::toBool($row['enabled']),
                'status' => $rowStatus,
                'attributesIndexed' => $this->decodeJson($row['attributes_indexed']),
                'isPrimary' => self::toBool($row['is_primary']),
                'position' => self::toInt($row['position']),
            ];
        }

        $base = '/api/categories/'.$thisId.'/products';
        $view = [
            '@id' => $base.'?page='.$page.'&itemsPerPage='.$itemsPerPage,
            '@type' => 'hydra:PartialCollectionView',
            'hydra:first' => $base.'?page=1&itemsPerPage='.$itemsPerPage,
        ];
        $lastPage = $totalItems === 0 ? 1 : (int) ceil($totalItems / $itemsPerPage);
        $view['hydra:last'] = $base.'?page='.$lastPage.'&itemsPerPage='.$itemsPerPage;
        if ($page < $lastPage) {
            $view['hydra:next'] = $base.'?page='.($page + 1).'&itemsPerPage='.$itemsPerPage;
        }
        if ($page > 1) {
            $view['hydra:previous'] = $base.'?page='.($page - 1).'&itemsPerPage='.$itemsPerPage;
        }

        return new JsonResponse([
            '@context' => '/api/contexts/Product',
            '@id' => $base,
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => $totalItems,
            'hydra:member' => $members,
            'hydra:view' => $view,
        ]);
    }

    private function decodeJson(mixed $value): mixed
    {
        if (!\is_string($value) || '' === $value) {
            return [];
        }
        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private static function toInt(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && '' !== $value) {
            return (int) $value;
        }

        return 0;
    }

    private static function asString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value)) {
            return (string) $value;
        }

        return '';
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_int($value)) {
            return 1 === $value;
        }
        if (\is_string($value)) {
            return 't' === $value || 'true' === $value || '1' === $value;
        }

        return false;
    }
}
