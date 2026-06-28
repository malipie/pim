<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The JSON data type of a {@see \App\Integration\Generic\Domain\Entity\RemoteField}
 * as detected from a response sample (ADR-0022, epic APIC, ticket APIC-P2-02).
 *
 * Mirrors the JSON type system. The schema-discovery service (APIC-P2-04)
 * infers it from sampled values; the mapping screen (APIC-P2-08/09) uses it to
 * flag type compatibility against the target PIM attribute.
 */
enum RemoteFieldDataType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Number = 'number';
    case Boolean = 'boolean';
    case Object = 'object';
    case Array = 'array';
    case Null = 'null';

    /** Whether the type is a JSON scalar (not object/array/null). */
    public function isScalar(): bool
    {
        return match ($this) {
            self::String, self::Integer, self::Number, self::Boolean => true,
            self::Object, self::Array, self::Null => false,
        };
    }
}
