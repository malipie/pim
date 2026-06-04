<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Repository\AttributeOptionRepositoryInterface;

/**
 * `select` AttributeType validator.
 *
 * Rule: the picked `option_code` must exist in the attribute's canonical
 * option set. #1261 — that set is the live `attribute_options` table
 * (resolved via {@see AttributeOptionRepositoryInterface}); the legacy
 * `validation_rules['option_codes']` mirror is used only as a fallback
 * when no repository is wired (e.g. `AttributeValueValidator::default()`
 * called bare in a unit test). When neither source lists any code the
 * validator checks the shape only — a freshly created select with no
 * options yet must not reject every write.
 */
final readonly class SelectValidator implements AttributeValueValidatorInterface
{
    public function __construct(
        private ?AttributeOptionRepositoryInterface $options = null,
    ) {
    }

    public function validate(Attribute $attribute, array $value): array
    {
        $code = $value['option_code'] ?? null;
        if (!\is_string($code) || '' === $code) {
            return [new ValidationError('value.option_code', 'select.expected_string', 'Select value must include a non-empty option_code.')];
        }

        $allowed = $this->resolveAllowedCodes($attribute);
        if (null !== $allowed && !\in_array($code, $allowed, true)) {
            return [new ValidationError(
                'value.option_code',
                'select.unknown_option',
                \sprintf('Option "%s" is not a valid option for this attribute.', $code),
            )];
        }

        return [];
    }

    /**
     * The canonical option set: live `attribute_options` rows when a repo is
     * wired and the attribute has any options, otherwise the legacy
     * `validation_rules['option_codes']`. `null` = nothing to enforce.
     *
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
