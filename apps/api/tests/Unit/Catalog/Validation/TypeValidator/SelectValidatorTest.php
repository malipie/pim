<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Validation\TypeValidator;

use App\Catalog\Application\Validation\TypeValidator\SelectValidator;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SelectValidatorTest extends TestCase
{
    private SelectValidator $validator;
    private Attribute $attribute;

    protected function setUp(): void
    {
        $this->validator = new SelectValidator();
        $this->attribute = new Attribute('color', ['pl' => 'Kolor'], AttributeType::Select);
    }

    #[Test]
    public function optionCodeStringPasses(): void
    {
        self::assertSame([], $this->validator->validate($this->attribute, ['option_code' => 'red']));
    }

    #[Test]
    public function emptyOrMissingOptionCodeFails(): void
    {
        self::assertSame('select.expected_string', $this->validator->validate($this->attribute, [])[0]->code);
        self::assertSame('select.expected_string', $this->validator->validate($this->attribute, ['option_code' => ''])[0]->code);
    }

    #[Test]
    public function unknownOptionRejectedWhenAllowlistConfigured(): void
    {
        $this->attribute->updateValidationRules(['option_codes' => ['red', 'green', 'blue']]);

        $ok = $this->validator->validate($this->attribute, ['option_code' => 'red']);
        $bad = $this->validator->validate($this->attribute, ['option_code' => 'magenta']);

        self::assertSame([], $ok);
        self::assertSame('select.unknown_option', $bad[0]->code);
    }
}
