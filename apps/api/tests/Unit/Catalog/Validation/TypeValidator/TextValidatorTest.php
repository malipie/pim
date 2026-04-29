<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Validation\TypeValidator;

use App\Catalog\Application\Validation\TypeValidator\TextValidator;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextValidatorTest extends TestCase
{
    private TextValidator $validator;
    private Attribute $attribute;

    protected function setUp(): void
    {
        $this->validator = new TextValidator();
        $this->attribute = new Attribute('name', ['pl' => 'Nazwa'], AttributeType::Text);
    }

    #[Test]
    public function plainStringPasses(): void
    {
        self::assertSame([], $this->validator->validate($this->attribute, ['value' => 'hello']));
    }

    #[Test]
    public function nonStringValueFails(): void
    {
        $errors = $this->validator->validate($this->attribute, ['value' => 42]);

        self::assertCount(1, $errors);
        self::assertSame('text.expected_string', $errors[0]->code);
    }

    #[Test]
    public function maxLengthIsEnforcedInUtf8Characters(): void
    {
        $this->attribute->setValidationRules(['max_length' => 3]);

        // 4 utf-8 characters (with polish diacritics).
        $errors = $this->validator->validate($this->attribute, ['value' => 'łóść']);

        self::assertCount(1, $errors);
        self::assertSame('text.too_long', $errors[0]->code);
    }

    #[Test]
    public function minLengthIsEnforced(): void
    {
        $this->attribute->setValidationRules(['min_length' => 5]);

        $errors = $this->validator->validate($this->attribute, ['value' => 'hi']);

        self::assertSame('text.too_short', $errors[0]->code);
    }

    #[Test]
    public function patternMustMatchTheWholeValue(): void
    {
        $this->attribute->setValidationRules(['pattern' => '/^[A-Z]{3}$/']);

        $ok = $this->validator->validate($this->attribute, ['value' => 'ABC']);
        $bad = $this->validator->validate($this->attribute, ['value' => 'abc']);

        self::assertSame([], $ok);
        self::assertSame('text.pattern_mismatch', $bad[0]->code);
    }
}
