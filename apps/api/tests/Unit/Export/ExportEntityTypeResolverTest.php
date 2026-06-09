<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Export\Presentation\Support\ExportEntityTypeResolver;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * EXR-04 (#1380) — entity_type / object_type_id validation rules.
 *
 * Exercises {@see ExportEntityTypeResolver} directly so the exact messages and
 * exception types are asserted without depending on how the HTTP layer renders
 * the RFC 7807 detail.
 */
final class ExportEntityTypeResolverTest extends TestCase
{
    #[Test]
    public function defaultsToProductWhenEntityTypeAbsent(): void
    {
        $selection = $this->makeResolver()->resolve([]);

        self::assertSame(ExportEntityType::Product, $selection->entityType);
        self::assertNull($selection->objectTypeId);
    }

    #[Test]
    public function acceptsCustomModuleWithCustomObjectType(): void
    {
        $objectTypeId = Uuid::v7();
        $custom = new ObjectType('services', ObjectKind::Custom, ['pl' => 'Usługi']);

        $selection = $this->makeResolver($custom)->resolve([
            'entity_type' => 'custom_module',
            'object_type_id' => $objectTypeId->toRfc4122(),
        ]);

        self::assertSame(ExportEntityType::CustomModule, $selection->entityType);
        self::assertTrue($objectTypeId->equals($selection->objectTypeId));
    }

    #[Test]
    public function rejectsCustomModuleWithoutObjectTypeId(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('object_type_id is required');

        $this->makeResolver()->resolve(['entity_type' => 'custom_module']);
    }

    #[Test]
    public function rejectsCustomModuleWhenObjectTypeNotFound(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('does not reference a known ObjectType');

        $this->makeResolver(null)->resolve([
            'entity_type' => 'custom_module',
            'object_type_id' => Uuid::v7()->toRfc4122(),
        ]);
    }

    #[Test]
    public function rejectsCustomModulePointingAtBuiltInObjectType(): void
    {
        $builtIn = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkty']);
        $builtIn->markBuiltIn();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('is_built_in=false');

        $this->makeResolver($builtIn)->resolve([
            'entity_type' => 'custom_module',
            'object_type_id' => Uuid::v7()->toRfc4122(),
        ]);
    }

    #[Test]
    public function rejectsObjectTypeIdForNonCustomType(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('object_type_id is not allowed');

        $this->makeResolver()->resolve([
            'entity_type' => 'categories',
            'object_type_id' => Uuid::v7()->toRfc4122(),
        ]);
    }

    #[Test]
    public function rejectsUnknownEntityType(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported entity_type');

        $this->makeResolver()->resolve(['entity_type' => 'widgets']);
    }

    #[Test]
    public function rejectsMalformedObjectTypeId(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('object_type_id must be an RFC 4122 UUID');

        $this->makeResolver()->resolve([
            'entity_type' => 'custom_module',
            'object_type_id' => 'not-a-uuid',
        ]);
    }

    #[Test]
    public function structuralTypeRejectsNonAllScope(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('target_scope must be "all"');

        $this->makeResolver()->assertScopeAllowed(ExportEntityType::Categories, ExportTargetScope::Filter);
    }

    #[Test]
    public function scopeRulesAllowStructuralAllAndAnyCatalogScope(): void
    {
        $resolver = $this->makeResolver();

        $resolver->assertScopeAllowed(ExportEntityType::Categories, ExportTargetScope::All);
        $resolver->assertScopeAllowed(ExportEntityType::Product, ExportTargetScope::Filter);
        $resolver->assertScopeAllowed(ExportEntityType::CustomModule, ExportTargetScope::Selected);

        $this->expectNotToPerformAssertions();
    }

    private function makeResolver(?ObjectType $found = null): ExportEntityTypeResolver
    {
        $repository = new class($found) implements ObjectTypeRepositoryInterface {
            public function __construct(private ?ObjectType $found)
            {
            }

            public function findById(Uuid $id): ?ObjectType
            {
                return $this->found;
            }

            public function findByCode(string $code, Tenant $tenant): ?ObjectType
            {
                throw new LogicException('not used');
            }

            public function findByKind(ObjectKind $kind, Tenant $tenant): array
            {
                throw new LogicException('not used');
            }

            public function findAllByTenant(Tenant $tenant): array
            {
                throw new LogicException('not used');
            }

            public function findBuiltInByKind(ObjectKind $kind, Tenant $tenant): ?ObjectType
            {
                throw new LogicException('not used');
            }

            public function save(ObjectType $objectType): void
            {
                throw new LogicException('not used');
            }

            public function remove(ObjectType $objectType): void
            {
                throw new LogicException('not used');
            }
        };

        return new ExportEntityTypeResolver($repository);
    }
}
