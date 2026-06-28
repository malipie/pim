<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The paging strategy a {@see \App\Integration\Generic\Domain\Entity\RemoteEndpoint}
 * uses to walk a list response (ADR-0022, epic APIC, ticket APIC-P2-03).
 *
 * Stored as the `strategy` key of the endpoint's `pagination` JSONB envelope;
 * the concrete drivers live in `Infrastructure/Http/Pagination`.
 */
enum PaginationStrategyName: string
{
    case None = 'none';
    case Offset = 'offset';
    case Page = 'page';
    case Cursor = 'cursor';
    case LinkHeader = 'link_header';
}
