<?php

declare(strict_types=1);

namespace App\Export\Application\Builder;

use RuntimeException;

/**
 * IMP2-1.6 (#1469, R-47) — raised when an export references a channel-scoped
 * column (`price.shopify`, `name.pl.shopify`) whose channel no longer exists
 * in the tenant.
 *
 * Replaces the pre-1.6 silent "blank column" degradation: a stale channel
 * code combined with `clear_if_empty` on the destination could wipe a whole
 * catalogue. The export now fails loudly — 422 at preflight
 * ({@see \App\Export\Presentation\Controller\ExportPreflightController}) and
 * defensively here in the build pipeline if preflight was bypassed.
 */
final class UnresolvedExportChannelException extends RuntimeException
{
    /**
     * @param list<string> $channelCodes the unresolvable channel codes
     */
    public function __construct(public readonly array $channelCodes)
    {
        parent::__construct(\sprintf(
            'Export references channel(s) that no longer exist in this tenant: %s.',
            implode(', ', $channelCodes),
        ));
    }
}
