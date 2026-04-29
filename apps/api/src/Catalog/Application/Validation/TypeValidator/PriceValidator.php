<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `price` AttributeType validator. Shape: `{amount: numeric, currency:
 * 'PLN' | 'EUR' | …}`.
 *
 * Rules:
 *   - `min_amount` (numeric)
 *   - `currencies` (list<string>) — restrict the allowed currency set
 *
 * Cross-check that the currency exists in the global `currencies` table
 * is the API layer's job.
 */
final class PriceValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $errors = [];
        $amount = $value['amount'] ?? null;
        $currency = $value['currency'] ?? null;

        if (!\is_int($amount) && !\is_float($amount)) {
            $errors[] = new ValidationError('value.amount', 'price.expected_numeric_amount', 'Price amount must be int or float.');
        }
        if (!\is_string($currency) || 1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = new ValidationError('value.currency', 'price.expected_iso_currency', 'Price currency must be a 3-letter ISO 4217 code (uppercase).');
        }

        $rules = $attribute->getValidationRules();
        $min = $rules['min_amount'] ?? null;
        if ((\is_int($amount) || \is_float($amount)) && (\is_int($min) || \is_float($min)) && $amount < $min) {
            $errors[] = new ValidationError('value.amount', 'price.below_min', \sprintf('Price amount %s below min %s.', (string) $amount, (string) $min));
        }
        $allowed = $rules['currencies'] ?? null;
        if (\is_string($currency) && \is_array($allowed) && !\in_array($currency, $allowed, true)) {
            $errors[] = new ValidationError('value.currency', 'price.unsupported_currency', \sprintf('Currency "%s" is not in the configured currencies set.', $currency));
        }

        return $errors;
    }
}
