<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\ApiPlatform;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Catalog\Infrastructure\ApiPlatform\KindAwareSerializerContextBuilder;
use App\Catalog\Infrastructure\ApiPlatform\ObjectKindRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class KindAwareSerializerContextBuilderTest extends TestCase
{
    #[Test]
    public function passesThroughWhenOperationHasNoKindExtraProperty(): void
    {
        $builder = $this->builderReturning(['groups' => ['object:read']]);

        $context = $builder->createFromRequest(new Request(), true);

        self::assertSame(['object:read'], $context['groups']);
        self::assertArrayNotHasKey('kind', $context);
    }

    #[Test]
    public function mergesPerKindGroupsAndStampsKindWhenOperationHasIt(): void
    {
        $operation = new Get()->withExtraProperties(['kind' => 'product']);
        $builder = $this->builderReturning([
            'groups' => ['object:read'],
            'operation' => $operation,
        ]);

        $context = $builder->createFromRequest(new Request(), true);

        self::assertSame('product', $context['kind']);
        self::assertSame(['object:read', 'object:read:product'], $context['groups']);
    }

    #[Test]
    public function unknownKindValueIsIgnoredAndContextLeftUntouched(): void
    {
        $operation = new Get()->withExtraProperties(['kind' => 'banana']);
        $builder = $this->builderReturning([
            'groups' => ['object:read'],
            'operation' => $operation,
        ]);

        $context = $builder->createFromRequest(new Request(), true);

        self::assertArrayNotHasKey('kind', $context);
        self::assertSame(['object:read'], $context['groups']);
    }

    #[Test]
    public function customKindFallsBackToSharedGroupAndStillStampsKindMarker(): void
    {
        $operation = new Get()->withExtraProperties(['kind' => 'custom']);
        $builder = $this->builderReturning([
            'groups' => ['object:read'],
            'operation' => $operation,
        ]);

        $context = $builder->createFromRequest(new Request(), true);

        self::assertSame('custom', $context['kind']);
        self::assertSame(['object:read'], $context['groups']);
    }

    #[Test]
    public function singleStringGroupIsPromotedToList(): void
    {
        $operation = new Get()->withExtraProperties(['kind' => 'category']);
        $builder = $this->builderReturning([
            'groups' => 'object:read',
            'operation' => $operation,
        ]);

        $context = $builder->createFromRequest(new Request(), true);

        self::assertSame(['object:read', 'object:read:category'], $context['groups']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function builderReturning(array $context): KindAwareSerializerContextBuilder
    {
        $inner = new class($context) implements SerializerContextBuilderInterface {
            /**
             * @param array<string, mixed> $context
             */
            public function __construct(private readonly array $context)
            {
            }

            public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
            {
                return $this->context;
            }
        };

        return new KindAwareSerializerContextBuilder($inner, new ObjectKindRouter());
    }
}
