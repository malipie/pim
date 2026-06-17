<?php

declare(strict_types=1);

namespace App\Tests\Architecture\PHPStan;

use App\PHPStan\Rules\FlushWithoutClearInLoopRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * AUD-011 (W1-8) — failing-test-first proof that FlushWithoutClearInLoopRule
 * catches the FrankenPHP worker-mode OOM pattern (flush() in a batch loop
 * without clear() in a Messenger handler) and leaves the sanctioned patterns
 * (flush+clear, flush-after-loop, AbstractBatchHandler, non-handler service,
 * nested-loop-with-clear) untouched.
 *
 * @extends RuleTestCase<FlushWithoutClearInLoopRule>
 */
#[Group('architecture')]
final class FlushWithoutClearInLoopRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'EntityManager::flush() inside a loop in a Messenger handler is not paired with a clear() '
        .'in the same loop. In FrankenPHP worker mode the Doctrine identity map accumulates across '
        .'flushes and a batch loop without clear() drives the worker into OOM (architektura §3.10, R-25). '
        .'Extend App\\Shared\\Application\\AbstractBatchHandler and flush via flushAndClear(), '
        .'or call $entityManager->clear() after flush() inside the loop.';

    protected function getRule(): Rule
    {
        return new FlushWithoutClearInLoopRule();
    }

    #[Test]
    public function flagsForeachFlushWithoutClear(): void
    {
        $this->analyse(
            [__DIR__.'/Fixtures/BadForeachFlushHandler.php'],
            [[self::MESSAGE, 28]],
        );
    }

    #[Test]
    public function flagsForLoopFlushWithoutClear(): void
    {
        $this->analyse(
            [__DIR__.'/Fixtures/BadForLoopFlushHandler.php'],
            [[self::MESSAGE, 28]],
        );
    }

    #[Test]
    public function flagsNestedLoopFlushOnceOnInnerLine(): void
    {
        // Reported exactly once, on the inner flush line — not once per level.
        $this->analyse(
            [__DIR__.'/Fixtures/BadNestedLoopFlushHandler.php'],
            [[self::MESSAGE, 29]],
        );
    }

    #[Test]
    public function passesFlushClearInLoop(): void
    {
        $this->analyse([__DIR__.'/Fixtures/GoodFlushClearInLoopHandler.php'], []);
    }

    #[Test]
    public function passesFlushAfterLoop(): void
    {
        $this->analyse([__DIR__.'/Fixtures/GoodFlushAfterLoopHandler.php'], []);
    }

    #[Test]
    public function passesAbstractBatchHandlerSubclass(): void
    {
        $this->analyse([__DIR__.'/Fixtures/GoodBatchHandler.php'], []);
    }

    #[Test]
    public function passesNonHandlerSyncService(): void
    {
        $this->analyse([__DIR__.'/Fixtures/GoodSyncServiceWithLoopFlush.php'], []);
    }

    #[Test]
    public function passesNestedLoopWithClearInOuterLoop(): void
    {
        $this->analyse([__DIR__.'/Fixtures/GoodNestedLoopWithClearHandler.php'], []);
    }
}
