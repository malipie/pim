<?php

declare(strict_types=1);

namespace App\Tests\Api\Asset;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression for #1143 — asset thumbnails (`<img src="/api/assets/{id}/preview">`)
 * returned 403 for every request because the controller carried
 * `#[RequiresPermission]` while the route is `PUBLIC_ACCESS`: an `<img>`
 * tag cannot send the Bearer token, so the request is anonymous and the
 * EndpointGuardListener rejected it before the controller ran.
 *
 * The endpoint must be reachable anonymously — an unknown asset yields a
 * 404 (handler reached), never a 401/403 (guard short-circuit).
 */
final class AssetPreviewPublicAccessApiTest extends CatalogApiTestCase
{
    #[Test]
    public function previewIsReachableAnonymouslyAndReturns404ForUnknownAsset(): void
    {
        // No Authorization header — mirrors a browser `<img>` request.
        $client = static::createClient();
        $client->request('GET', '/api/assets/00000000-0000-0000-0000-000000000000/preview');

        // 404 = controller ran and found no asset. 401/403 would mean the
        // RBAC guard blocked the anonymous request (the #1143 regression).
        self::assertResponseStatusCodeSame(404);
    }
}
