<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * UI-02.4 (#294) — POST `/api/products/{id}/duplicate`.
 *
 * Clones a product (CatalogObject of `kind=product`) into a new row
 * under the current tenant. Body schema (all optional):
 *   - `sku` (string) — overrides the auto-generated `{src}-COPY-N`.
 *   - `with_categories` (bool, default `true`) — clone category links
 *     (when the Association edge ships in #UI-02.6 follow-up; current
 *     MVP catalog wires categories through `objects.parent_id` for
 *     `kind=category`, so the flag is reserved + ignored gracefully).
 *   - `with_assets` (bool, default `false`) — reserved (asset link
 *     cloning lands with the DAM epic UI-05).
 *   - `with_relations` (bool, default `false`) — reserved (related
 *     product cloning lands with epik 09 in Faza 1).
 *
 * Out of MVP slice: rate-limit, audit `created_via=duplicate`
 * metadata, variant master protection (depends on UI-02.6
 * `master_object_id`).
 */
final class DuplicateProductController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly ObjectValueRepositoryInterface $values,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        '/api/products/{id}/duplicate',
        name: 'pim_products_duplicate',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['POST'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        $source = $this->objects->findById(Uuid::fromString($id));
        if (!$source instanceof CatalogObject || ObjectKind::Product !== $source->getKind()) {
            throw new NotFoundHttpException(\sprintf('Product %s not found.', $id));
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $rawSku = $body['sku'] ?? null;
        $newSku = \is_string($rawSku) && '' !== $rawSku
            ? $rawSku
            : $this->generateUniqueCopySku($source);

        if (null !== $this->objects->findByCode($newSku, ObjectKind::Product, $tenant)) {
            throw new ConflictHttpException(\sprintf('Product with SKU "%s" already exists.', $newSku));
        }

        $copy = new CatalogObject($source->getObjectType(), $newSku);
        $this->objects->save($copy);

        // Clone every ObjectValue row from source. Provenance resets to
        // `manual` with the new context (per ticket — copy is a new
        // canonical write, not an inherited import/agent value).
        foreach ($this->values->findByObject($source) as $sourceValue) {
            $cloned = new ObjectValue(
                object: $copy,
                attribute: $sourceValue->getAttribute(),
                value: $sourceValue->getValue(),
                provenance: Provenance::Manual,
            );
            $cloned->changeChannelId($sourceValue->getChannelId());
            $cloned->changeLocale($sourceValue->getLocale());
            $this->values->save($cloned);
        }

        return new JsonResponse([
            'id' => $copy->getId()->toRfc4122(),
            'code' => $copy->getCode(),
            'kind' => $copy->getKind()->value,
            'source_id' => $source->getId()->toRfc4122(),
        ], Response::HTTP_CREATED);
    }

    private function generateUniqueCopySku(CatalogObject $source): string
    {
        $tenant = $source->getTenant();
        if (null === $tenant) {
            throw new BadRequestHttpException('Source product is missing tenant context.');
        }

        $base = $source->getCode();
        $candidate = $base.'-COPY-1';
        $counter = 1;
        while (null !== $this->objects->findByCode($candidate, ObjectKind::Product, $tenant)) {
            ++$counter;
            if ($counter > 9999) {
                throw new ConflictHttpException(\sprintf('Could not allocate a duplicate SKU for "%s".', $base));
            }
            $candidate = \sprintf('%s-COPY-%d', $base, $counter);
        }

        return $candidate;
    }
}
