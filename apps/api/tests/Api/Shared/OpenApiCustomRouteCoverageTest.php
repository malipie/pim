<?php

declare(strict_types=1);

namespace App\Tests\Api\Shared;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AUD-043 — custom #[Route] surface was absent from OpenAPI (31 paths);
 * decorator lifts coverage to the full router surface.
 *
 * Before this ticket the exported spec only described resource-backed
 * operations, so `docs/api-spec/v0.json` carried 31 paths while the router
 * exposes ~228 custom `/api/*` routes (auth, MFA, password reset,
 * invitations, bulk-edit, export, import, assets, super-admin, RBAC). This
 * regression suite pins both halves of the contract:
 *   - the custom surface is now documented (path count well above the old 31);
 *   - representative hand-written routes carry a tag and at least one response;
 *   - resource-backed paths produced by the inner factory are untouched.
 */
final class OpenApiCustomRouteCoverageTest extends KernelTestCase
{
    /**
     * Representative custom `#[Route]` controllers confirmed present in the
     * router (`debug:router`). None use the `.{_format}` API Platform suffix.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function customRouteProvider(): iterable
    {
        yield 'super-admin break-glass' => ['/api/admin/break-glass'];
        yield 'agent cmd-k' => ['/api/agent/cmd-k'];
        yield 'api tokens' => ['/api/api-tokens'];
        yield 'asset upload' => ['/api/assets/upload'];
        yield 'export columns' => ['/api/exports/columns'];
        yield '2fa disable' => ['/api/auth/2fa/disable'];
    }

    #[Test]
    public function decoratorLiftsCoverageWellAboveTheLegacyThirtyOnePaths(): void
    {
        $paths = $this->exportPaths();

        self::assertGreaterThan(
            150,
            \count($paths->getPaths()),
            'Custom #[Route] surface must be documented — the pre-AUD-043 spec had only 31 paths.',
        );
    }

    #[Test]
    #[DataProvider('customRouteProvider')]
    public function customRouteIsDocumentedWithTagAndResponse(string $path): void
    {
        $pathItem = $this->exportPaths()->getPath($path);

        self::assertInstanceOf(
            PathItem::class,
            $pathItem,
            \sprintf('Custom route %s must appear in the OpenAPI document.', $path),
        );

        $operation = $this->firstOperation($pathItem);
        self::assertInstanceOf(
            Operation::class,
            $operation,
            \sprintf('Path %s must expose at least one operation.', $path),
        );

        self::assertNotEmpty($operation->getTags(), \sprintf('Operation %s must carry a tag.', $path));
        self::assertNotEmpty(
            $operation->getResponses(),
            \sprintf('Operation %s must declare at least one response.', $path),
        );
        self::assertSame(
            'custom-route',
            $operation->getExtensionProperties()['x-pim-source'] ?? null,
            \sprintf('Operation %s must be tagged x-pim-source: custom-route.', $path),
        );
    }

    /**
     * The decorator must never drop a resource-backed path the inner factory
     * already produced. `/api/products` is the Catalog sugar path.
     */
    #[Test]
    public function apiPlatformResourcePathSurvives(): void
    {
        self::assertInstanceOf(
            PathItem::class,
            $this->exportPaths()->getPath('/api/products'),
            'API Platform resource paths must remain after the custom-route decorator runs.',
        );
    }

    private function exportPaths(): Paths
    {
        self::bootKernel();
        $factory = self::getContainer()->get('api_platform.openapi.factory');
        self::assertInstanceOf(OpenApiFactoryInterface::class, $factory);

        return $factory()->getPaths();
    }

    private function firstOperation(PathItem $pathItem): ?Operation
    {
        return $pathItem->getGet()
            ?? $pathItem->getPost()
            ?? $pathItem->getPut()
            ?? $pathItem->getPatch()
            ?? $pathItem->getDelete()
            ?? $pathItem->getOptions()
            ?? $pathItem->getHead();
    }
}
