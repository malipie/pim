<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\ValueObject\ValidationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * IMP2-1.9 (#1472) — blocking is driven by SEVERITY (level === Error), not by
 * error type. Locks the full ImportErrorType × ImportLogLevel matrix so a
 * Warning can never block (the bug that made re-importing one's own export
 * reject every row).
 */
final class ValidationErrorTest extends TestCase
{
    /**
     * @return iterable<string, array{ImportErrorType, ImportLogLevel, bool}>
     */
    public static function matrix(): iterable
    {
        foreach (ImportErrorType::cases() as $type) {
            foreach (ImportLogLevel::cases() as $level) {
                $expected = ImportLogLevel::Error === $level;
                yield \sprintf('%s × %s', $type->value, $level->value) => [$type, $level, $expected];
            }
        }
    }

    #[Test]
    #[DataProvider('matrix')]
    public function blockingFollowsSeverityNotType(ImportErrorType $type, ImportLogLevel $level, bool $expectedBlocking): void
    {
        $error = new ValidationError(
            rowNumber: 1,
            sku: 'SKU-1',
            errorType: $type,
            level: $level,
            message: 'finding',
        );

        self::assertSame($expectedBlocking, $error->isRowBlocking());
    }

    #[Test]
    public function categoryNotFoundWarningNoLongerBlocks(): void
    {
        // Regression: pre-1.9 this was the ONLY non-blocking type; now any
        // Warning is non-blocking and any Error blocks.
        $warning = new ValidationError(1, 'S', ImportErrorType::CategoryNotFound, ImportLogLevel::Warning, 'm');
        $error = new ValidationError(1, 'S', ImportErrorType::CategoryNotFound, ImportLogLevel::Error, 'm');

        self::assertFalse($warning->isRowBlocking());
        self::assertTrue($error->isRowBlocking(), 'an Error-level finding blocks regardless of its type');
    }
}
