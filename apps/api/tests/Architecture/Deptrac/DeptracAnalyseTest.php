<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Deptrac;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Smoke test that the Deptrac ruleset stays green inside the test suite,
 * not just in CI. The CLI binary itself does the work; we only invoke it,
 * capture the exit code, and surface the same number of violations PHPUnit
 * sees (so a `bin/phpunit --testsuite=architecture` run is enough to catch
 * a freshly-introduced cross-BC import without relying on remote CI).
 *
 * The test is tagged with the `architecture` group; running it requires
 * the binary at vendor/bin/deptrac which is `composer install`'d on every
 * CI run + every developer setup.
 */
#[Group('architecture')]
final class DeptracAnalyseTest extends TestCase
{
    #[Test]
    public function rulesetPasses(): void
    {
        $process = new Process(
            command: ['vendor/bin/deptrac', 'analyse', '--no-progress', '--no-cache', '--report-uncovered'],
            cwd: \dirname(__DIR__, 3),
            timeout: 120,
        );
        $process->run();

        self::assertSame(
            0,
            $process->getExitCode(),
            "Deptrac analyse failed:\n".$process->getOutput().$process->getErrorOutput(),
        );
    }
}
