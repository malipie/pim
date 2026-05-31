<?php

declare(strict_types=1);

namespace App\Channel\Contracts;

use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * Cross-BC port for resolving a channel `code` to its id (#1154).
 *
 * Lets the Catalog context route per-channel attribute reads/writes
 * (`?channel=shopify`) to the right {@see \App\Catalog\Domain\Entity\ObjectValue}
 * scope without depending on Channel internals — Deptrac allows
 * Catalog_Internals → Channel_Contracts only.
 */
interface ChannelResolverInterface
{
    /**
     * The channel id for the given code within the tenant, or null when no
     * such channel exists (caller surfaces a 422).
     */
    public function resolveId(string $code, Tenant $tenant): ?Uuid;
}
