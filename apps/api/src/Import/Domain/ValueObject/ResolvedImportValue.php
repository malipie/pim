<?php

declare(strict_types=1);

namespace App\Import\Domain\ValueObject;

/**
 * One mapped cell ready for persistence: the attribute code it targets,
 * the optional locale parsed from a dotted column header (`name.pl` →
 * locale `pl`), and the raw string value.
 *
 * Replaces the flat `attribute_code → value` map the importer used before
 * #1130 — that shape collided when several localised columns (`name.pl`,
 * `name.en`) targeted the same attribute. A list of resolved values lets
 * the creator write one {@see \App\Catalog\Domain\Entity\ObjectValue} per
 * (attribute, locale) pair.
 */
final readonly class ResolvedImportValue
{
    public function __construct(
        public string $attributeCode,
        public ?string $locale,
        public ?string $rawValue,
    ) {
    }
}
