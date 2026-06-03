<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Catalog\Domain\Validator\IdentifierUniquenessValidator;
use App\Shared\Domain\Tenant;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Map a flat `{attribute_code => value}` payload into `ObjectValue`
 * rows on a given `CatalogObject` (#45 / 0.4.5).
 *
 * Every value is wrapped in the canonical `{value: ...}` JSONB shape
 * the per-AttributeType validators (#39) expect. Provenance defaults
 * to `Manual` because the admin UI is the only write surface in MVP;
 * phase 2 agents will pass `Provenance::Agent` (reserved enum case).
 *
 * Unknown attribute codes are silently dropped — adopting strict
 * mode would force every fixture / migration to enumerate the
 * built-in seed and is overkill for MVP. The admin UI's dynamic
 * schema picker (epic 0.6) will surface dropped keys before the
 * request lands here.
 *
 * The `AttributesIndexedSyncListener` (#38) keeps
 * `CatalogObject.attributes_indexed` in sync through the same flush;
 * read side reads from the denormalised cache, write side flows
 * through this service.
 */
final readonly class ObjectAttributesUpserter
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private ObjectValueRepositoryInterface $values,
        private IdentifierUniquenessValidator $identifierUniqueness,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param ?string              $locale    active locale scope (#1148). When set
     *                                        AND the attribute is localizable AND the
     *                                        locale is not the tenant's primary, the
     *                                        value is written to that locale; otherwise
     *                                        it lands on the global (locale=null) row.
     *                                        Default locale === global keeps legacy data
     *                                        and non-localizable attributes shared.
     * @param ?Uuid                $channelId resolved active channel scope (#1154). When set
     *                                        AND the attribute is scopable, the value is written
     *                                        to that channel; otherwise it lands on the global
     *                                        (channel=null) row.
     */
    public function upsert(
        CatalogObject $object,
        array $payload,
        Provenance $provenance = Provenance::Manual,
        ?string $locale = null,
        ?Uuid $channelId = null,
    ): void {
        $tenant = $object->getTenant();
        if (!$tenant instanceof Tenant) {
            // Tenant is stamped on the parent during prePersist — for a
            // fresh aggregate built inside the same flush context the
            // listener has already run by the time this service is called.
            return;
        }

        $isNonPrimaryLocale = null !== $locale && $locale !== $tenant->getPrimaryLocale();

        foreach ($payload as $code => $rawValue) {
            if ('' === $code) {
                continue;
            }

            $attribute = $this->attributes->findByCode($code, $tenant);
            if (!$attribute instanceof Attribute) {
                continue;
            }

            $targetLocale = $isNonPrimaryLocale && $attribute->isLocalizable() ? $locale : null;
            $targetChannel = null !== $channelId && $attribute->isScopable() ? $channelId : null;

            $jsonbValue = $this->wrapValue($rawValue);

            // #1179 — identifier values are unique per ObjectType. Pre-check
            // here for a clean 409 (the DB partial unique index is the
            // race-proof backstop). Skip empty/non-string values — clearing
            // an identifier is allowed.
            if (AttributeType::Identifier === $attribute->getType()) {
                $candidate = $jsonbValue['value'] ?? null;
                if (\is_string($candidate) && '' !== $candidate
                    && $this->identifierUniqueness->isDuplicate($object, $attribute, $candidate)) {
                    throw new ConflictHttpException(\sprintf(
                        'Identifier "%s" is already assigned to another %s.',
                        $candidate,
                        $object->getObjectType()->getCode(),
                    ));
                }
            }

            $existing = $this->values->findOneByScope($object, $attribute, $targetChannel, $targetLocale);
            if ($existing instanceof ObjectValue) {
                $existing->updateValue($jsonbValue);
                $existing->changeProvenance($provenance);
                $this->values->save($existing);

                continue;
            }

            $value = new ObjectValue(
                object: $object,
                attribute: $attribute,
                value: $jsonbValue,
                provenance: $provenance,
                channelId: $targetChannel,
                locale: $targetLocale,
            );
            $this->values->save($value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function wrapValue(mixed $rawValue): array
    {
        // The canonical JSONB shape (per ObjectValue::$value docblock).
        // Localised arrays (`{pl: '...', en: '...'}`) and metric/price
        // dicts pass through unchanged so the per-type validator (#39)
        // can read them with their structure intact.
        if (\is_array($rawValue)) {
            $normalised = [];
            foreach ($rawValue as $key => $value) {
                $normalised[(string) $key] = $value;
            }

            return $normalised;
        }

        return ['value' => $rawValue];
    }
}
