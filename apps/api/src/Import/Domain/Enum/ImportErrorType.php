<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * Validation error categories surfaced in the wizard's Step 3 preview
 * and the post-import CSV report. Names mirror spec §5.4 / §7.5 so the
 * wizard's "did you mean" copy maps 1:1 to the backend tag.
 */
enum ImportErrorType: string
{
    case MissingRequired = 'missing_required';
    case DuplicateSkuInFile = 'duplicate_sku_in_file';
    case DuplicateSkuInDb = 'duplicate_sku_in_db';
    case InvalidType = 'invalid_type';
    case InvalidValue = 'invalid_value';
    case ImageNotFound = 'image_not_found';
    case ImageFormatUnsupported = 'image_format_unsupported';
}
