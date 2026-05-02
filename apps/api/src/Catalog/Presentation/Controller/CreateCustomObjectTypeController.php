<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\ObjectTypeService;
use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\Exception\ObjectTypeCodeConflictException;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * UI-02 follow-up — POST `/api/object_types` for custom kinds.
 *
 * The base ApiResource for `ObjectType` is read-only (ADR-009 §schema +
 * `apps/api/src/Catalog/Infrastructure/ApiPlatform/Resource/ObjectType.xml`).
 * Custom ObjectType creation needed a thin write surface so the modeling
 * UI Create dialog has a target — this controller delegates straight to
 * `ObjectTypeService::create(kind=custom)`, which already enforces:
 *   - feature flag (`pim.catalog.enable_custom_object_types`) — flip the
 *     env var `CATALOG_ENABLE_CUSTOM_OBJECT_TYPES` to gate per environment;
 *   - tenant scope (TenantAssignmentListener stamps the row);
 *   - schema invariants (label JSONB, code uniqueness via DB index).
 *
 * Built-in / system kinds (product / category / asset / brand) stay
 * read-only — operators manage them only via DB seed.
 */
final class CreateCustomObjectTypeController
{
    public function __construct(
        private readonly ObjectTypeService $service,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Priority 200 wins against the API Platform read-only Get collection
     * route on the same path.
     */
    #[Route(
        '/api/object_types',
        name: 'pim_object_types_create_custom',
        methods: ['POST'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $code = $body['code'] ?? null;
        if (!\is_string($code) || '' === trim($code)) {
            throw new BadRequestHttpException('code is required.');
        }
        $code = trim($code);

        $rawLabel = $body['label'] ?? null;
        if (!\is_array($rawLabel) || [] === $rawLabel) {
            throw new BadRequestHttpException('label is required (per-locale map, e.g. {"pl": "...", "en": "..."}).');
        }
        $label = [];
        foreach ($rawLabel as $locale => $value) {
            if (\is_string($locale) && \is_string($value) && '' !== trim($value)) {
                $label[$locale] = trim($value);
            }
        }
        if ([] === $label) {
            throw new BadRequestHttpException('label must contain at least one non-empty entry.');
        }

        // Reject collisions early with a clear 409 (the DB unique index
        // would also catch it but the message would be opaque).
        if (null !== $this->objectTypes->findByCode($code, $tenant)) {
            throw new ConflictHttpException(\sprintf('ObjectType with code "%s" already exists.', $code));
        }

        // VIEW-01 (#372) — wizard sends icon / color / hierarchical /
        // hasVariants / abstract together with the basic identity. All
        // optional; defaults match the entity defaults.
        $icon = $this->stringOrNull($body['icon'] ?? null);
        $color = $this->stringOrNull($body['color'] ?? null);
        $hierarchical = (bool) ($body['hierarchical'] ?? false);
        $hasVariants = (bool) ($body['hasVariants'] ?? false);
        $abstract = (bool) ($body['abstract'] ?? false);

        try {
            $objectType = $this->service->create(
                code: $code,
                kind: ObjectKind::Custom,
                label: $label,
                builtIn: false,
                icon: $icon,
                color: $color,
                hierarchical: $hierarchical,
                hasVariants: $hasVariants,
                abstract: $abstract,
            );
        } catch (DisabledFeatureException $e) {
            throw new HttpException(Response::HTTP_FORBIDDEN, $e->getMessage(), $e);
        } catch (ObjectTypeCodeConflictException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
        }

        return new JsonResponse([
            'id' => $objectType->getId()->toRfc4122(),
            'code' => $objectType->getCode(),
            'kind' => $objectType->getKind()->value,
            'label' => $objectType->getLabel(),
            'builtIn' => $objectType->isBuiltIn(),
            'icon' => $objectType->getIcon(),
            'color' => $objectType->getColor(),
            'hierarchical' => $objectType->isHierarchical(),
            'hasVariants' => $objectType->hasVariants(),
            'abstract' => $objectType->isAbstract(),
            'allowedParentTypeIds' => $objectType->getAllowedParentTypeIds(),
            'schemaVersion' => $objectType->getSchemaVersion(),
        ], Response::HTTP_CREATED);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
