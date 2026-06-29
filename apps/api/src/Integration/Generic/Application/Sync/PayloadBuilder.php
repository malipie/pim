<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

use App\Integration\Generic\Domain\Entity\FieldMapping;

/**
 * Builds an outbound push body from serialized PIM values and the connection's
 * outbound field mappings (APIC-P3-06).
 *
 * Each outbound mapping writes its value into the body at the remote field's
 * dot path (`$.price.amount` → `{"price": {"amount": …}}`), so a 1:1 mapping
 * reconstructs the remote's nested shape. Inbound-only mappings are ignored.
 */
final readonly class PayloadBuilder
{
    /**
     * @param array<string, string> $values   attributeCode => serialized value
     * @param list<FieldMapping>    $mappings
     *
     * @return array<string, mixed>
     */
    public function build(array $values, array $mappings): array
    {
        $body = [];
        foreach ($mappings as $mapping) {
            if (!$mapping->getDirection()->appliesOutbound()) {
                continue;
            }
            if (!\array_key_exists($mapping->getPimTarget(), $values)) {
                continue;
            }
            self::setPath($body, $mapping->getRemoteFieldPath(), $values[$mapping->getPimTarget()]);
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function setPath(array &$body, string $path, string $value): void
    {
        $segments = self::segments($path);
        if ([] === $segments) {
            return;
        }

        $ref = &$body;
        $last = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !\is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref[$last] = $value;
    }

    /**
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        $trimmed = ltrim(ltrim(trim($path), '$'), '.');
        if ('' === $trimmed) {
            return [];
        }

        return array_values(array_filter(explode('.', $trimmed), static fn (string $s): bool => '' !== $s));
    }
}
