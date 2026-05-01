<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Rule\VisibleWhenRule;
use App\Catalog\Domain\Rule\VisibleWhenRuleEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VisibleWhenRuleTest extends TestCase
{
    #[Test]
    public function fromArrayBuildsEqualsRule(): void
    {
        $rule = VisibleWhenRule::fromArray([
            'field' => 'requires_referral',
            'operator' => 'equals',
            'value' => true,
        ]);

        self::assertSame('requires_referral', $rule->field);
        self::assertSame('equals', $rule->operator);
        self::assertTrue($rule->value);
    }

    #[Test]
    public function unsupportedOperatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VisibleWhenRule('field', 'in', ['a', 'b']);
    }

    #[Test]
    public function emptyFieldIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VisibleWhenRule(' ', 'equals', true);
    }

    #[Test]
    public function fromArrayRequiresAllKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VisibleWhenRule::fromArray(['field' => 'x']);
    }

    #[Test]
    public function evaluatorReturnsFalseWhenFieldMissing(): void
    {
        $evaluator = new VisibleWhenRuleEvaluator();
        $rule = VisibleWhenRule::equals('requires_referral', true);

        self::assertFalse($evaluator->isVisible($rule, []));
    }

    #[Test]
    public function evaluatorMatchesScalarValueAfterUnwrappingHybridShape(): void
    {
        $evaluator = new VisibleWhenRuleEvaluator();
        $rule = VisibleWhenRule::equals('requires_referral', true);

        self::assertTrue($evaluator->isVisible($rule, [
            'requires_referral' => ['value' => true],
        ]));
        self::assertFalse($evaluator->isVisible($rule, [
            'requires_referral' => ['value' => false],
        ]));
    }

    #[Test]
    public function evaluatorMatchesSelectOptionCode(): void
    {
        $evaluator = new VisibleWhenRuleEvaluator();
        $rule = VisibleWhenRule::equals('material', 'wood');

        self::assertTrue($evaluator->isVisible($rule, [
            'material' => ['option_code' => 'wood'],
        ]));
        self::assertFalse($evaluator->isVisible($rule, [
            'material' => ['option_code' => 'metal'],
        ]));
    }

    #[Test]
    public function toArrayRoundTripsRule(): void
    {
        $rule = VisibleWhenRule::equals('material', 'wood');

        self::assertSame([
            'field' => 'material',
            'operator' => 'equals',
            'value' => 'wood',
        ], $rule->toArray());
    }
}
