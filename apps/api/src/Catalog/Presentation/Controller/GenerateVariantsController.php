<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
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
 * UI-02.6 (#296) — `POST /api/products/{master_id}/generate-variants`.
 *
 * Generates child product rows (variants) for a master row by walking
 * the cartesian product of axis values. Body schema:
 *   - `axes` (required) — `{color: ["red","blue"], size: ["S","M","L"]}`.
 *   - `sku_template` (optional) — default `{master_sku}-{values_joined}`,
 *     e.g. `TST-001-RED-S`.
 *
 * Each generated variant is a `CatalogObject` with:
 *   - `objectType` and `kind=product` inherited from master,
 *   - `parent` set to master (existing self-FK semantics —
 *     `kind='product'` parent_id is the variant master per the
 *     `CatalogObject` docblock),
 *   - `code` derived from the SKU template.
 *
 * The master also gets its `variant_axes` JSONB column updated so the
 * UI-02.18 axes editor reads back the canonical definition. SKU
 * collisions are skipped (counted but not persisted) — re-running the
 * endpoint after a partial failure is idempotent.
 *
 * Out of MVP slice (Faza 1+): variant-level value seeding (price /
 * stock), axes editor PATCH, master-with-variants delete protection,
 * `Attribute.level=master|variant` enum, `VariantValueResolver`
 * inheritance service.
 */
final class GenerateVariantsController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        '/api/products/{master_id}/generate-variants',
        name: 'pim_products_generate_variants',
        requirements: ['master_id' => self::UUID_REGEX],
        methods: ['POST'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $master_id, Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        $master = $this->objects->findById(Uuid::fromString($master_id));
        if (!$master instanceof CatalogObject || ObjectKind::Product !== $master->getKind()) {
            throw new NotFoundHttpException(\sprintf('Master product %s not found.', $master_id));
        }
        if (null !== $master->getParent()) {
            throw new ConflictHttpException('Cannot generate variants from a variant — pick the master row.');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $axesRaw = $body['axes'] ?? null;
        if (!\is_array($axesRaw) || [] === $axesRaw) {
            throw new BadRequestHttpException('axes must be a non-empty object {axis_code: [values]}.');
        }
        /** @var array<string, list<scalar>> $axes */
        $axes = [];
        foreach ($axesRaw as $axisCode => $values) {
            if (!\is_array($values) || [] === $values) {
                throw new BadRequestHttpException(\sprintf('axes.%s must be a non-empty list of values.', $axisCode));
            }
            $clean = [];
            foreach ($values as $v) {
                if (\is_scalar($v)) {
                    $clean[] = $v;
                }
            }
            if ([] === $clean) {
                throw new BadRequestHttpException(\sprintf('axes.%s must contain scalar values.', $axisCode));
            }
            $axes[(string) $axisCode] = $clean;
        }

        $skuTemplate = $body['sku_template'] ?? null;

        $combinations = $this->cartesianProduct($axes);

        $created = [];
        $skipped = [];
        foreach ($combinations as $combination) {
            $sku = $this->renderSku($master->getCode(), $combination, \is_string($skuTemplate) ? $skuTemplate : null);
            if (null !== $this->objects->findByCode($sku, ObjectKind::Product, $tenant)) {
                $skipped[] = $sku;

                continue;
            }
            $variant = new CatalogObject($master->getObjectType(), $sku);
            $variant->assignParent($master);
            $this->objects->save($variant);
            $created[] = ['sku' => $sku, 'axis_values' => $combination];
        }

        // Persist the canonical axes definition on the master.
        $axesDefinition = [];
        foreach ($axes as $axisCode => $values) {
            $axesDefinition[] = [
                'code' => $axisCode,
                'values' => $values,
            ];
        }
        $master->declareVariantAxes($axesDefinition);
        $this->objects->save($master);

        return new JsonResponse([
            'master_id' => $master->getId()->toRfc4122(),
            'created_count' => \count($created),
            'skipped_count' => \count($skipped),
            'created' => $created,
            'skipped_existing' => $skipped,
        ], Response::HTTP_CREATED);
    }

    /**
     * @param array<string, list<scalar>> $axes
     *
     * @return list<array<string, scalar>> ordered combinations
     */
    private function cartesianProduct(array $axes): array
    {
        $result = [[]];
        foreach ($axes as $axisCode => $values) {
            $next = [];
            foreach ($result as $combination) {
                foreach ($values as $value) {
                    $next[] = $combination + [$axisCode => $value];
                }
            }
            $result = $next;
        }

        /* @var list<array<string, scalar>> $result */
        return $result;
    }

    /**
     * @param array<string, scalar> $combination
     */
    private function renderSku(string $masterSku, array $combination, ?string $template): string
    {
        if (null === $template) {
            $parts = array_map(static fn ($v): string => strtoupper((string) $v), array_values($combination));

            return $masterSku.'-'.implode('-', $parts);
        }

        $rendered = str_replace('{master_sku}', $masterSku, $template);
        foreach ($combination as $axis => $value) {
            $rendered = str_replace('{'.$axis.'}', (string) $value, $rendered);
        }

        return $rendered;
    }
}
