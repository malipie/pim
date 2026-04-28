<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use RuntimeException;

/**
 * Raised when an operation is gated behind a feature flag that is currently
 * off. The factory methods describe the call site so log lines + RFC 7807
 * responses (when API Platform exposure lands) carry intent.
 */
final class DisabledFeatureException extends RuntimeException
{
    public static function customObjectTypesDisabled(): self
    {
        return new self(
            'Creating ObjectType with kind="custom" is disabled in MVP. '
            .'Set parameter `pim.catalog.enable_custom_object_types` to enable (phase 2 work).'
        );
    }
}
