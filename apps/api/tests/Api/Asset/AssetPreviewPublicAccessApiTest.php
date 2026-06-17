<?php

declare(strict_types=1);

namespace App\Tests\Api\Asset;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression for #1143 + AUD-006 (#1576).
 *
 * #1143: asset thumbnails (`<img src="/api/assets/{id}/preview">`) once
 * returned 403 for every request because the controller carried
 * `#[RequiresPermission]` while the route is `PUBLIC_ACCESS`: an `<img>`
 * tag cannot send the Bearer token, so the request is anonymous and the
 * EndpointGuardListener rejected it before the controller ran. The
 * endpoint therefore must NOT be RBAC-gated.
 *
 * AUD-006: before the hardening, "not RBAC-gated" had degraded into "open
 * to anyone with the id" — an anonymous request reached the row
 * cross-tenant and (with a blob present) streamed the bytes. The fix
 * gates the endpoint on a short-lived HMAC signature instead: the
 * controller is still reachable without a Bearer token, but an
 * unsigned request is rejected with 403, never served.
 *
 * This suite pins the negative half of that contract — an anonymous,
 * UNSIGNED request must be 403 (not 200, not 404). The positive half
 * (a valid signature streams the bytes) lives in
 * {@see AssetPreviewSignedUrlApiTest}.
 */
final class AssetPreviewPublicAccessApiTest extends CatalogApiTestCase
{
    #[Test]
    public function unsignedAnonymousRequestIsForbiddenNotFound(): void
    {
        // No Authorization header AND no signature — mirrors a raw `<img>`
        // request that never went through the signing read API.
        $client = static::createClient();
        $client->request('GET', '/api/assets/00000000-0000-0000-0000-000000000000/preview');

        // 403 = the signature guard blocked the request before any row
        // loaded. 404 would mean the handler ran and authorised by
        // id-knowledge alone (the AUD-006 hole); 200 would mean it
        // streamed bytes.
        self::assertResponseStatusCodeSame(403);
    }
}
