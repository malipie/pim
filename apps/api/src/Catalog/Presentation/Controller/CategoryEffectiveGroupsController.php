<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use App\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use ValueError;

/**
 * UI-08.14 (#269) — `GET /api/categories/{id}/effective-groups?objectTypeKind=service`.
 *
 * Returns the effective AttributeGroup list a hypothetical object of
 * the given target ObjectType would see if placed under this category.
 * Powers the `<EffectiveAttributesPreview>` widget on the category
 * detail page — the killer feature competitors lack.
 *
 * Reuses {@see EffectiveAttributeGroupResolver::resolveForCategoryPreview}
 * directly (UI-08.4 domain service); the layered resolution + dedup
 * logic is identical to the one we feed to the form-renderer when an
 * object exists. Output schema mirrors `ObjectFormSchema` from UI-08.4
 * so the frontend can reuse the same projection.
 *
 * Query params:
 *   - `objectTypeKind` (required) — `product` / `category` / `asset` /
 *     `brand` / future `custom`. Identifies which ObjectType (per
 *     tenant) to resolve groups for.
 */
final class CategoryEffectiveGroupsController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly EffectiveAttributeGroupResolver $resolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        '/api/categories/{id}/effective-groups',
        name: 'pim_categories_effective_groups',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $kindParam = $request->query->get('objectTypeKind');
        if (!\is_string($kindParam) || '' === $kindParam) {
            throw new BadRequestHttpException('objectTypeKind query parameter is required.');
        }
        try {
            $kind = ObjectKind::from($kindParam);
        } catch (ValueError $e) {
            throw new BadRequestHttpException(\sprintf('Unknown objectTypeKind "%s".', $kindParam), $e);
        }

        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        $type = $this->objectTypes->findBuiltInByKind($kind, $tenant);
        if (null === $type) {
            throw new NotFoundHttpException(\sprintf('Built-in ObjectType for kind "%s" not found.', $kindParam));
        }

        $category = $this->objects->findById(Uuid::fromString($id));
        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category "%s" was not found.', $id));
        }

        $groups = $this->resolver->resolveForCategoryPreview($type, $category);
        $byGroup = $this->resolver->loadGroupAttributes($groups);

        $effective = [];
        foreach ($groups as $position => $group) {
            $junctions = $byGroup[$group->getId()->toRfc4122()] ?? [];
            $attributes = [];
            foreach ($junctions as $j) {
                $attribute = $j->getAttribute();
                $attributes[] = [
                    'id' => $attribute->getId()->toRfc4122(),
                    'code' => $attribute->getCode(),
                    'type' => $attribute->getType()->value,
                    'label' => $attribute->getLabel(),
                    'is_system' => $attribute->isSystem(),
                    'position' => $j->getPosition(),
                    'is_required_in_group' => $j->isRequiredInGroup(),
                    'visible_when' => $j->getVisibleWhen(),
                ];
            }
            $effective[] = [
                'id' => $group->getId()->toRfc4122(),
                'code' => $group->getCode(),
                'label' => $group->getLabel(),
                'description' => $group->getDescription(),
                'icon' => $group->getIcon(),
                'color' => $group->getColor(),
                'is_system_group' => $group->isSystemGroup(),
                'auto_attached' => $group->isAutoAttached(),
                'position' => $position,
                'attributes' => $attributes,
            ];
        }

        return new JsonResponse([
            'categoryId' => $category->getId()->toRfc4122(),
            'objectType' => [
                'id' => $type->getId()->toRfc4122(),
                'code' => $type->getCode(),
                'kind' => $type->getKind()->value,
                'label' => $type->getLabel(),
            ],
            'effectiveGroups' => $effective,
        ]);
    }
}
