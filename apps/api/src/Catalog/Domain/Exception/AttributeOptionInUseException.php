<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Raised when an AttributeOption deletion is attempted but the option is
 * still referenced by `object_values` rows. The FE `<AttributeValuesPage>`
 * surfaces this as a toast suggesting the operator either deprecate the
 * option (keep history) or migrate values to a replacement option first.
 */
final class AttributeOptionInUseException extends UnprocessableEntityHttpException
{
    public function __construct(public readonly string $attributeCode, public readonly string $optionCode, public readonly int $instances)
    {
        parent::__construct(\sprintf(
            'AttributeOption "%s.%s" is referenced by %d object_values rows — deprecate or migrate before deleting.',
            $attributeCode,
            $optionCode,
            $instances,
        ));
    }
}
