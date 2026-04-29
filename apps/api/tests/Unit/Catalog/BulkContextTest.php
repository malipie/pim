<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Application\BulkContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BulkContextTest extends TestCase
{
    #[Test]
    public function defaultsToFalse(): void
    {
        $context = new BulkContext();
        self::assertFalse($context->isBulk());
    }

    #[Test]
    public function setBulkTogglesFlag(): void
    {
        $context = new BulkContext();

        $context->setBulk(true);
        self::assertTrue($context->isBulk());

        $context->setBulk(false);
        self::assertFalse($context->isBulk());
    }
}
