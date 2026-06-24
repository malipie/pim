<?php

declare(strict_types=1);

namespace App\Asset\Contracts;

use App\Asset\Contracts\Exception\UnsupportedMediaFormatException;

/**
 * IMP2-1.12 — the shared "binary → Asset" ingest seam: magic-byte image
 * validation, SHA-256 content-hash dedup, and storage. The single entry
 * point every media source funnels through — HTTP download (IMP2-1.12),
 * ZIP extraction (IMP2-1.13), prefix/suffix URL build (IMP2-3.4) — so the
 * dedup + storage contract lives in one place. Lives in Asset\Contracts so
 * the Import context depends only on the port (deptrac: Import → Asset_Contracts),
 * never on Asset\Application internals.
 *
 * The active tenant is taken from the TenantContext (the implementation
 * stamps storage + the Asset row tenant-scoped).
 */
interface AssetIngestorInterface
{
    /**
     * Validate + dedup + store the binary at $absolutePath.
     *
     * @param string  $absolutePath     local path to the already-fetched bytes
     * @param string  $originalFilename filename to record on the Asset (e.g. URL basename)
     * @param ?string $folderCode       logical library folder for a NEWLY stored asset
     *                                  (e.g. `product-<uuid>` so imported images land in the
     *                                  object's folder, matching manual upload). A content-hash
     *                                  dedup hit keeps the existing asset's folder.
     *
     * @throws UnsupportedMediaFormatException when the bytes are not jpg/png/webp
     */
    public function ingest(string $absolutePath, string $originalFilename, ?string $folderCode = null): AssetIngestResult;
}
