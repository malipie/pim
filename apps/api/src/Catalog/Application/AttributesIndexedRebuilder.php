<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds and persists `CatalogObject.attributes_indexed` (JSONB cache)
 * + `CatalogObject.completeness` from the canonical `ObjectValue` rows.
 *
 * Called both:
 *   - synchronously from the Doctrine listener after a single-edit flush,
 *   - asynchronously from the bulk-rebuild Messenger handler.
 *
 * Indexing strategy: every `ObjectValue` row contributes one entry under
 * its Attribute.code with the JSONB `value` payload. Per-channel /
 * per-locale variants overlay the global value — we keep the latest
 * (channel-first, then locale-first ordering) so the cache reflects the
 * most-specific entry. The full lookup-priority logic lives in the read
 * path (ApiResource serializer in #41); here we just lay them down in
 * a stable ordering.
 *
 * Completeness reads `ObjectType.completeness_rules.required` (a list of
 * Attribute codes) and reports how many of those have at least one
 * non-null value. The result is `{global: <int 0-100>}`. Per-channel /
 * locale completeness lands in #41 alongside the channel-aware reader.
 */
final readonly class AttributesIndexedRebuilder
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<int, ObjectValue>|null $values Optional pre-loaded
     *                                             values for the object — saves one round-trip when the
     *                                             listener already has them in the unit of work. Pass null
     *                                             to make the rebuilder fetch fresh.
     */
    public function rebuild(CatalogObject $object, ?array $values = null): void
    {
        $values ??= $this->em->getRepository(ObjectValue::class)
            ->findBy(['object' => $object]);

        $indexed = [];
        foreach ($values as $value) {
            $code = $value->getAttribute()->getCode();
            // Single global row wins by default; channel/locale overrides
            // overlay in deterministic order. The serializer in #41 picks
            // the most specific reading.
            $indexed[$code] = $value->getValue();
        }

        $object->setAttributesIndexed($indexed);

        // Completeness: required-list / present-count. Empty rules → 100.
        $rules = $object->getObjectType()->getCompletenessRules();
        $required = \is_array($rules['required'] ?? null) ? $rules['required'] : [];
        if ([] === $required) {
            $object->setCompleteness(['global' => 100]);

            return;
        }

        $present = 0;
        foreach ($required as $code) {
            if (\is_string($code) && \array_key_exists($code, $indexed)) {
                ++$present;
            }
        }
        $pct = (int) round(100 * $present / \count($required));
        $object->setCompleteness(['global' => $pct]);
    }
}
