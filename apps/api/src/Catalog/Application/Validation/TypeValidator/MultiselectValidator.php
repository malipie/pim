<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Repository\AttributeOptionRepositoryInterface;

/**
 * `multiselect` AttributeType validator.
 *
 * Rules: `option_codes` (list<string>), `min_count` / `max_count` (int).
 * #1261 — each picked code must exist in the attribute's canonical option
 * set (live `attribute_options` table via {@see AttributeOptionRepositoryInterface}),
 * falling back to `validation_rules['option_codes']` when no repo is wired.
 */
final readonly class MultiselectValidator implements AttributeValueValidatorInterface
{
    public function __construct(
        private ?AttributeOptionRepositoryInterface $options = null,
    ) {
    }

    public function validate(Attribute $attribute, array $value): array
    {
        $codes = $value['option_codes'] ?? null;
        if (!\is_array($codes)) {
            return [new ValidationError('value.option_codes', 'multiselect.expected_list', 'Multiselect value must be a list of option codes.')];
        }

        $errors = [];
        foreach ($codes as $i => $code) {
            if (!\is_string($code) || '' === $code) {
                $errors[] = new ValidationError(\sprintf('value.option_codes.%d', $i), 'multiselect.expected_string_item', 'Each option code must be a non-empty string.');
            }
        }

        $allowed = $this->resolveAllowedCodes($attribute);
        if (null !== $allowed) {
            foreach ($codes as $i => $code) {
                if (\is_string($code) && '' !== $code && !\in_array($code, $allowed, true)) {
                    $errors[] = new ValidationError(\sprintf('value.option_codes.%d', $i), 'multiselect.unknown_option', \sprintf('Option "%s" is not a valid option for this attribute.', $code));
                }
            }
        }

        $rules = $attribute->getValidationRules();
        $count = \count($codes);
        $min = $rules['min_count'] ?? null;
        if (\is_int($min) && $count < $min) {
            $errors[] = new ValidationError('value.option_codes', 'multiselect.too_few', \sprintf('At least %d option(s) required, got %d.', $min, $count));
        }
        $max = $rules['max_count'] ?? null;
        if (\is_int($max) && $count > $max) {
            $errors[] = new ValidationError('value.option_codes', 'multiselect.too_many', \sprintf('At most %d option(s) allowed, got %d.', $max, $count));
        }

        return $errors;
    }

    /**
     * @return list<string>|null
     */
    private function resolveAllowedCodes(Attribute $attribute): ?array
    {
        if (null !== $this->options) {
            $dbCodes = $this->options->findCodesByAttribute($attribute);
            if ([] !== $dbCodes) {
                return $dbCodes;
            }
        }

        $allowed = $attribute->getValidationRules()['option_codes'] ?? null;

        return \is_array($allowed) ? array_values(array_filter($allowed, 'is_string')) : null;
    }
}
