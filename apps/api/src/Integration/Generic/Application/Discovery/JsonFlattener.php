<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Discovery;

use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;

/**
 * Flattens a decoded JSON record into a list of {@see DiscoveredField} dot paths
 * (ADR-0022, epic APIC, ticket APIC-P2-04).
 *
 * Nested objects are walked recursively (`$.price.amount`); lists and scalars
 * are leaves typed from the sampled value. A depth and field-count cap keep a
 * pathological payload from exploding the proposal. Sample values are truncated
 * — and since the only inputs are public list responses (never credentials),
 * nothing sensitive is captured.
 */
final class JsonFlattener
{
    private const int MAX_DEPTH = 12;
    private const int MAX_FIELDS = 500;
    private const int SAMPLE_MAX_CHARS = 200;

    /**
     * @param array<array-key, mixed> $record
     *
     * @return list<DiscoveredField>
     */
    public function flatten(array $record, string $prefix = '$'): array
    {
        $fields = [];
        $this->walk($record, $prefix, 0, $fields);

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<DiscoveredField>   $fields
     */
    private function walk(array $node, string $prefix, int $depth, array &$fields): void
    {
        foreach ($node as $key => $value) {
            if (\count($fields) >= self::MAX_FIELDS) {
                return;
            }

            $path = $prefix.'.'.$key;

            if (\is_array($value) && [] !== $value && !array_is_list($value) && $depth < self::MAX_DEPTH) {
                $this->walk($value, $path, $depth + 1, $fields);

                continue;
            }

            $fields[] = new DiscoveredField($path, self::inferType($value), self::sample($value));
        }
    }

    private static function inferType(mixed $value): RemoteFieldDataType
    {
        return match (true) {
            \is_bool($value) => RemoteFieldDataType::Boolean,
            \is_int($value) => RemoteFieldDataType::Integer,
            \is_float($value) => RemoteFieldDataType::Number,
            \is_string($value) => RemoteFieldDataType::String,
            null === $value => RemoteFieldDataType::Null,
            \is_array($value) && array_is_list($value) => RemoteFieldDataType::Array,
            \is_array($value) => RemoteFieldDataType::Object,
            default => RemoteFieldDataType::String,
        };
    }

    private static function sample(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value) || \is_float($value) || \is_string($value)) {
            return mb_substr((string) $value, 0, self::SAMPLE_MAX_CHARS);
        }

        $encoded = json_encode($value);

        return false === $encoded ? null : mb_substr($encoded, 0, self::SAMPLE_MAX_CHARS);
    }
}
