<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application;

use App\ApiConfigurator\Domain\Entity\ApiProfile;

/**
 * Prunes a serialized response to only the fields the profile
 * publishes. Reused by the test endpoint (`#95`) and (in a follow-up)
 * a normalizer that fires on canonical paths when the request is
 * authenticated as an API key.
 *
 * Two axes of filtering:
 *   - **Attributes**: when `includedAttributes` is non-empty the
 *     `attributes`/`attributesIndexed` map is reduced to the listed
 *     codes; codes outside the list disappear from the payload.
 *   - **ObjectTypes**: `objectTypeIds` narrows which sugar paths the
 *     profile exposes. The filter drops `kind` discriminator data
 *     for objects whose ObjectType is not on the profile's list.
 *     (Class-level guarding lives in the controller; the filter
 *     leaves the row data intact when the kind is allowed.)
 */
final readonly class ApiProfileResponseFilter
{
    /**
     * @param array<string, mixed> $row a serialized CatalogObject row
     *
     * @return array<string, mixed>
     */
    public function project(array $row, ApiProfile $profile): array
    {
        $included = $profile->getIncludedAttributes();
        if ([] === $included) {
            return $row;
        }

        foreach (['attributes', 'attributesIndexed'] as $key) {
            if (!\array_key_exists($key, $row) || !\is_array($row[$key])) {
                continue;
            }
            $row[$key] = array_intersect_key($row[$key], array_flip($included));
        }

        return $row;
    }
}
