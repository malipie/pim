<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `color` AttributeType validator (#1177).
 *
 * Stores the colour as a string in `object_values.value->>'value'`.
 * Rule from `Attribute.validation_rules`:
 *   - `color_format` (string): `hex` (default) → `#RRGGBB`;
 *     `rgb` → `rgb(r, g, b)` with each channel 0-255.
 */
final class ColorValidator implements AttributeValueValidatorInterface
{
    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';
    private const RGB_PATTERN = '/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/';

    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return [new ValidationError('value.value', 'color.expected_string', 'Color value must be a non-empty string.')];
        }

        $rules = $attribute->getValidationRules();
        $format = \is_string($rules['color_format'] ?? null) ? $rules['color_format'] : 'hex';

        if ('rgb' === $format) {
            if (1 !== preg_match(self::RGB_PATTERN, $raw, $m)) {
                return [new ValidationError('value.value', 'color.invalid_rgb', \sprintf('Color "%s" is not a valid rgb(r, g, b) string.', $raw))];
            }
            foreach (\array_slice($m, 1) as $channel) {
                if ((int) $channel > 255) {
                    return [new ValidationError('value.value', 'color.channel_out_of_range', \sprintf('Color "%s" has an rgb channel above 255.', $raw))];
                }
            }

            return [];
        }

        if (1 !== preg_match(self::HEX_PATTERN, $raw)) {
            return [new ValidationError('value.value', 'color.invalid_hex', \sprintf('Color "%s" is not a valid #RRGGBB hex string.', $raw))];
        }

        return [];
    }
}
