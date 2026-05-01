<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectFormSchema;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use LogicException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Resolves the form-schema for an object — wraps {@see EffectiveAttributeGroupResolver}
 * with a cache layer so repeat reads from the admin (Refine boot + tab
 * switches) do not re-traverse the junction tables on every request.
 *
 * Cache key: `pim_form_schema_<tenant_id>_<object_id>_<schema_version>`.
 * `schema_version` of the ObjectType bumps on every metadata change, so a
 * stale entry self-invalidates as soon as the operator edits the model
 * — no manual key juggling. {@see ObjectFormSchemaCacheInvalidator} adds
 * a tag-based pattern invalidator on top for changes that do *not* bump
 * `schema_version` (e.g. group attachment changes on a category).
 *
 * Returns null when the object id is unknown — the controller maps that
 * to 404. Cross-tenant reads are blocked at the repository layer
 * (TenantFilter applied to `findById`).
 */
final readonly class GetObjectFormSchemaHandler
{
    public const string CACHE_TAG = 'pim_form_schema';
    public const int CACHE_TTL_SECONDS = 300;

    public function __construct(
        private CatalogObjectRepositoryInterface $repository,
        private EffectiveAttributeGroupResolver $resolver,
        private TagAwareCacheInterface $modelingCache,
    ) {
    }

    public function __invoke(GetObjectFormSchemaQuery $query): ?ObjectFormSchema
    {
        $object = $this->repository->findById($query->objectId);
        if (null === $object) {
            return null;
        }

        $tenant = $object->getTenant();
        if (null === $tenant) {
            throw new LogicException('CatalogObject hydrated without a tenant — corrupt fixture or persistence bug.');
        }

        $key = \sprintf(
            'pim_form_schema_%s_%s_%d',
            $tenant->getId()->toRfc4122(),
            $object->getId()->toRfc4122(),
            $object->getObjectType()->getSchemaVersion(),
        );

        return $this->modelingCache->get(
            $key,
            function (ItemInterface $item) use ($object): ObjectFormSchema {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);
                $item->tag([
                    self::CACHE_TAG,
                    self::CACHE_TAG.'.object_type.'.$object->getObjectType()->getId()->toRfc4122(),
                ]);

                return $this->build($object);
            },
        );
    }

    private function build(CatalogObject $object): ObjectFormSchema
    {
        $type = $object->getObjectType();
        $groups = $this->resolver->resolve($object);
        $byGroup = $this->resolver->loadGroupAttributes($groups);

        $effective = [];
        foreach ($groups as $position => $group) {
            $effective[] = $this->projectGroup($group, $byGroup[$group->getId()->toRfc4122()] ?? [], $position);
        }

        return new ObjectFormSchema(
            objectId: $object->getId()->toRfc4122(),
            objectType: [
                'id' => $type->getId()->toRfc4122(),
                'code' => $type->getCode(),
                'kind' => $type->getKind()->value,
                'label' => $type->getLabel(),
            ],
            effectiveGroups: $effective,
        );
    }

    /**
     * @param list<AttributeGroupAttribute> $junctions
     *
     * @return array<string, mixed>
     */
    private function projectGroup(AttributeGroup $group, array $junctions, int $position): array
    {
        $attributes = [];
        foreach ($junctions as $junction) {
            $attribute = $junction->getAttribute();
            $attributes[] = [
                'id' => $attribute->getId()->toRfc4122(),
                'code' => $attribute->getCode(),
                'type' => $attribute->getType()->value,
                'label' => $attribute->getLabel(),
                'help' => $attribute->getHelp(),
                'is_localizable' => $attribute->isLocalizable(),
                'is_scopable' => $attribute->isScopable(),
                'is_required' => $attribute->isRequired(),
                'is_system' => $attribute->isSystem(),
                'position' => $junction->getPosition(),
                'is_required_in_group' => $junction->isRequiredInGroup(),
                'visible_when' => $junction->getVisibleWhen(),
                'validation_rules' => $attribute->getValidationRules(),
            ];
        }

        return [
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
}
