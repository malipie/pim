<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `identifier` AttributeType validator (#1179) — EAN-13, GTIN-14, ISBN,
 * internal SKU. Stored as a single `string` in `value->>'value'`.
 *
 * Per-ObjectType *uniqueness* is enforced separately at the DB level
 * (trigger + partial unique index) with an application pre-check in
 * {@see \App\Catalog\Domain\Validator\IdentifierUniquenessValidator}; this
 * validator only covers the value's *shape*. Rules from
 * `Attribute.validation_rules`:
 *   - `pattern` (string): PCRE the identifier must match.
 *   - `format` (string): one of `ean13` | `gtin14` | `isbn13` | `isbn10`
 *     — digit count + check digit (GTIN mod-10 for the first three,
 *     ISBN-10 mod-11 for the last).
 */
final class IdentifierValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return [new ValidationError('value.value', 'identifier.expected_string', 'Identifier value must be a non-empty string.')];
        }

        $errors = [];
        $rules = $attribute->getValidationRules();

        $pattern = $rules['pattern'] ?? null;
        if (\is_string($pattern) && '' !== $pattern && 1 !== preg_match($pattern, $raw)) {
            $errors[] = new ValidationError('value.value', 'identifier.pattern_mismatch', \sprintf('Identifier does not match pattern %s.', $pattern));
        }

        $format = $rules['format'] ?? null;
        if (\is_string($format) && '' !== $format && !$this->matchesFormat($format, $raw)) {
            $errors[] = new ValidationError('value.value', 'identifier.invalid_format', \sprintf('Identifier "%s" is not a valid %s.', $raw, $format));
        }

        return $errors;
    }

    private function matchesFormat(string $format, string $raw): bool
    {
        return match ($format) {
            'ean13' => $this->isGtin($raw, 13),
            'gtin14' => $this->isGtin($raw, 14),
            'isbn13' => $this->isGtin($raw, 13) && (str_starts_with($raw, '978') || str_starts_with($raw, '979')),
            'isbn10' => $this->isIsbn10($raw),
            default => true, // unknown format key → no format constraint
        };
    }

    /**
     * GTIN family (EAN-13, GTIN-14, ISBN-13): all digits, fixed length,
     * mod-10 check digit.
     */
    private function isGtin(string $raw, int $length): bool
    {
        if (1 !== preg_match('/^\d{'.$length.'}$/', $raw)) {
            return false;
        }

        $digits = array_map('intval', str_split($raw));
        $check = (int) array_pop($digits);
        $sum = 0;
        // The right-most non-check digit carries weight 3, alternating 1/3.
        foreach (array_reverse($digits) as $i => $digit) {
            $sum += $digit * (0 === $i % 2 ? 3 : 1);
        }
        $computed = (10 - ($sum % 10)) % 10;

        return $computed === $check;
    }

    /**
     * ISBN-10: 9 digits + check (digit or `X` = 10), mod-11.
     */
    private function isIsbn10(string $raw): bool
    {
        if (1 !== preg_match('/^\d{9}[\dX]$/', $raw)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; ++$i) {
            $char = $raw[$i];
            $digit = 'X' === $char ? 10 : (int) $char;
            $sum += $digit * (10 - $i);
        }

        return 0 === $sum % 11;
    }
}
