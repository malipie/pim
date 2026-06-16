<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Service;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

/**
 * AUD-006 / #1576 — public port for minting and verifying the short-lived
 * HMAC-signed preview URLs that authorise `GET /api/assets/{id}/preview`.
 *
 * Lives in Asset\Contracts so the Catalog read surface (which signs the
 * denormalised `previewUrl` per request) depends only on this port, never
 * on Asset\Application internals (deptrac: Catalog → Asset_Contracts, see
 * ADR-0013). The implementation — {@see \App\Asset\Application\AssetPreviewUrlSigner}
 * — owns the TTL and the {@see \Symfony\Component\HttpFoundation\UriSigner}
 * wiring.
 *
 * The signature IS the auth factor (an `<img>` tag cannot attach a Bearer
 * header), so only an authenticated caller who can already read the asset
 * ever receives a signed URL, and the signature expires shortly after
 * issuance.
 */
interface AssetPreviewSigner
{
    /**
     * Returns a signed, relative preview URL for the asset id (RFC-4122).
     *
     * @param DateTimeImmutable|null $now test seam — when provided, the
     *                                    expiration is computed as $now + TTL
     *                                    so an expired URL can be produced
     *                                    deterministically in tests
     */
    public function sign(string $assetId, ?string $variant = null, ?DateTimeImmutable $now = null): string;

    /**
     * Verifies the signature + expiration carried by the request's path +
     * query string. Returns false for a missing, tampered, or expired
     * signature.
     */
    public function verify(Request $request): bool;
}
