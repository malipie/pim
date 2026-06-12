<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Map a flat `{attribute_code => value}` payload into `ObjectValue`
 * rows on a given `CatalogObject` (#45 / 0.4.5).
 *
 * IMP2-1.4 (#1466): the shared rule-set (canon normalisation, required,
 * per-type format validation, identifier uniqueness, scope routing) lives
 * in {@see ValueWriteCore} — this service is the thin per-request client
 * that maps violations to HTTP exceptions. The import path consumes the
 * same core through {@see BatchValueWriter}, which is what guarantees
 * "import validates exactly like the admin".
 *
 * Unknown attribute codes are silently dropped — see #45 for rationale.
 * The `AttributesIndexedSyncListener` (#38) keeps the denormalised cache
 * in sync through the same flush.
 */
final readonly class ObjectAttributesUpserter
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private ObjectValueRepositoryInterface $values,
        private ValueWriteCore $core,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param ?string              $locale    active locale scope (#1148); non-primary
     *                                        locale on a localizable attribute targets
     *                                        that locale row, otherwise the global row
     * @param ?Uuid                $channelId resolved active channel scope (#1154);
     *                                        applies to scopable attributes only
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

        foreach ($payload as $code => $rawValue) {
            if ('' === $code) {
                continue;
            }

            $attribute = $this->attributes->findByCode($code, $tenant);
            if (!$attribute instanceof Attribute) {
                continue;
            }

            [$targetLocale, $targetChannel] = $this->core->routeScope($attribute, $tenant, $locale, $channelId);

            $jsonbValue = $this->core->normalise($attribute->getType(), $rawValue);

            // #1350 — required attributes can never be explicitly emptied.
            $requiredViolation = $this->core->requiredViolation($attribute, $jsonbValue);
            if (null !== $requiredViolation) {
                throw new UnprocessableEntityHttpException($requiredViolation);
            }

            // #1216 / #1261 — per-type format + option membership.
            $formatViolations = $this->core->formatViolations($attribute, $jsonbValue);
            if ([] !== $formatViolations) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Attribute "%s": %s',
                    $code,
                    $formatViolations[0],
                ));
            }

            // #1179 — identifier uniqueness pre-check for a clean 409.
            $duplicate = $this->core->duplicateIdentifier($object, $attribute, $jsonbValue);
            if (null !== $duplicate) {
                throw new ConflictHttpException(\sprintf(
                    'Identifier "%s" is already assigned to another %s.',
                    $duplicate,
                    $object->getObjectType()->getCode(),
                ));
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
}
