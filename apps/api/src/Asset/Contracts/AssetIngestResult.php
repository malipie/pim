<?php

declare(strict_types=1);

namespace App\Asset\Contracts;

use Symfony\Component\Uid\Uuid;

/**
 * IMP2-1.12 — outcome of ingesting a binary into the asset library via
 * {@see AssetIngestorInterface}. Carries only the asset id (not the Asset
 * entity) so consumers in other bounded contexts stay decoupled from
 * Asset\Domain. `reused` is true when content-hash dedup matched an
 * existing asset (no new row / bytes written).
 */
final readonly class AssetIngestResult
{
    public function __construct(
        public Uuid $assetId,
        public bool $reused,
    ) {
    }
}
