<?php

declare(strict_types=1);

namespace App\Tests\Integration\Configuration;

use App\Kernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Locks in the prod-environment posture for API Platform's documentation
 * surface (audit MEDIUM-006).
 *
 * The interactive Swagger UI must NOT be reachable on the production
 * origin — integrators consume the OpenAPI document directly. The test
 * boots a fresh kernel pinned to `APP_ENV=prod` so the
 * `when@prod` overrides in `config/packages/api_platform.yaml` are
 * exercised end-to-end.
 *
 * The prod kernel is booted in a separate process so the test-only
 * service container cached by Foundry/KernelTestCase elsewhere is not
 * disturbed.
 */
final class ApiPlatformProdConfigTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function disablesSwaggerUiInProduction(): void
    {
        $kernel = self::bootProdKernel();
        $container = $kernel->getContainer();

        self::assertFalse(
            $container->getParameter('api_platform.enable_swagger_ui'),
            'Swagger UI must be disabled in prod (audit MEDIUM-006).',
        );
        self::assertFalse(
            $container->getParameter('api_platform.enable_re_doc'),
            'ReDoc must be disabled in prod (audit MEDIUM-006).',
        );
        self::assertTrue(
            $container->getParameter('api_platform.enable_docs'),
            'OpenAPI document must remain reachable for tooling.',
        );

        $kernel->shutdown();
    }

    private static function bootProdKernel(): Kernel
    {
        $kernel = new Kernel('prod', false);
        $kernel->boot();

        return $kernel;
    }
}
