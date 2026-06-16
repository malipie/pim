<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\HealthCheck\FolderPathGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * IMP2-2.8 (#1484) — folder-source containment guard. Proves `..` traversal and
 * symlinks that escape the base are rejected, so the health-check probe / source
 * save cannot be turned into a directory-enumeration tool.
 */
final class FolderPathGuardTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/pim-base-'.bin2hex(random_bytes(6));
        mkdir($this->base.'/sub', 0o777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->base.'/sub/link');
        @rmdir($this->base.'/sub');
        @rmdir($this->base);
    }

    #[Test]
    public function pathInsideBaseIsAllowed(): void
    {
        $guard = new FolderPathGuard($this->base);

        self::assertTrue($guard->isWithinBase($this->base));
        self::assertTrue($guard->isWithinBase($this->base.'/sub'));
    }

    #[Test]
    public function pathOutsideBaseIsRejected(): void
    {
        self::assertFalse(new FolderPathGuard($this->base)->isWithinBase('/etc'));
    }

    #[Test]
    public function parentTraversalEscapingBaseIsRejected(): void
    {
        self::assertFalse(new FolderPathGuard($this->base)->isWithinBase($this->base.'/../../etc'));
    }

    #[Test]
    public function symlinkEscapingBaseIsRejected(): void
    {
        $link = $this->base.'/sub/link';
        if (!@symlink('/etc', $link)) {
            self::markTestSkipped('symlink() unavailable in this environment.');
        }

        self::assertFalse(new FolderPathGuard($this->base)->isWithinBase($link));
    }

    #[Test]
    public function nonExistentPathIsRejected(): void
    {
        self::assertFalse(new FolderPathGuard($this->base)->isWithinBase($this->base.'/does-not-exist'));
    }
}
