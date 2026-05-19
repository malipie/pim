<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\Policy;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Application\Policy\AttributePermissionPolicy;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\AttributePermission;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-008 (#671) — unit coverage of AttributePermissionPolicy
 * resolution priority per PRD §3.5.
 *
 * Connection-level interactions are stubbed so each test exercises a
 * single branch of the resolution chain: broad gate / per-attribute /
 * per-group / role-default / multi-role merge.
 */
final class AttributePermissionPolicyTest extends TestCase
{
    private const string ROLE_ID = '01931700-0000-7000-8000-000000000001';
    private const string ROLE_ID_SECOND = '01931700-0000-7000-8000-000000000002';

    #[Test]
    public function returnsRestrictedWhenBroadGateMissing(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, []);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAllAssociative');
        $connection->expects(self::never())->method('fetchOne');

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertSame(AttributePermission::Restricted, $policy->resolvePermission($user, $attrId));
    }

    #[Test]
    public function perAttributeOverrideWinsOverGroupAndDefault(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);
        $connection = $this->stubConnection(
            roleIds: [self::ROLE_ID],
            perAttribute: AttributePermission::Edit->value,
        );

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertSame(AttributePermission::Edit, $policy->resolvePermission($user, $attrId));
    }

    #[Test]
    public function perGroupOverrideWinsWhenAttributeOverrideAbsent(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);
        $connection = $this->stubConnection(
            roleIds: [self::ROLE_ID],
            perGroup: AttributePermission::View->value,
        );

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertSame(AttributePermission::View, $policy->resolvePermission($user, $attrId));
    }

    #[Test]
    public function fallsBackToRoleDefault(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);
        $connection = $this->stubConnection(
            roleIds: [self::ROLE_ID],
            roleDefault: AttributePermission::View->value,
        );

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertSame(AttributePermission::View, $policy->resolvePermission($user, $attrId));
    }

    #[Test]
    public function takesMostPermissiveAcrossMultipleRoles(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);

        // First role: per-attribute Restricted. Second role: per-group Edit.
        // Most permissive wins → Edit.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturn([
                ['role_id' => self::ROLE_ID],
                ['role_id' => self::ROLE_ID_SECOND],
            ]);

        $connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params) {
                if (str_contains($sql, 'FROM role_attribute_permissions')) {
                    return self::ROLE_ID === $params['role_id'] ? AttributePermission::Restricted->value : false;
                }
                if (str_contains($sql, 'FROM role_attribute_group_permissions')) {
                    return self::ROLE_ID_SECOND === $params['role_id'] ? AttributePermission::Edit->value : false;
                }
                if (str_contains($sql, 'FROM roles WHERE id')) {
                    return AttributePermission::Restricted->value;
                }

                return false;
            });

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertSame(AttributePermission::Edit, $policy->resolvePermission($user, $attrId));
    }

    #[Test]
    public function returnsRestrictedWhenUserCarriesNoRoles(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertSame(AttributePermission::Restricted, $policy->resolvePermission($user, $attrId));
    }

    #[Test]
    public function canEditAttributeReflectsEditPermission(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.edit']);
        $connection = $this->stubConnection(
            roleIds: [self::ROLE_ID],
            perAttribute: AttributePermission::Edit->value,
        );

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertTrue($policy->canEditAttribute($user, $attrId));
        self::assertTrue($policy->canViewAttribute($user, $attrId));
    }

    #[Test]
    public function canEditFalseWhenOnlyViewGranted(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);
        $connection = $this->stubConnection(
            roleIds: [self::ROLE_ID],
            perAttribute: AttributePermission::View->value,
        );

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertFalse($policy->canEditAttribute($user, $attrId));
        self::assertTrue($policy->canViewAttribute($user, $attrId));
    }

    #[Test]
    public function canViewFalseWhenRestricted(): void
    {
        $user = $this->user();
        $attrId = Uuid::v7();

        $resolver = $this->resolverWith($user, ['products.view']);
        $connection = $this->stubConnection(
            roleIds: [self::ROLE_ID],
            perAttribute: AttributePermission::Restricted->value,
        );

        $policy = new AttributePermissionPolicy($connection, $resolver);

        self::assertFalse($policy->canViewAttribute($user, $attrId));
        self::assertFalse($policy->canEditAttribute($user, $attrId));
    }

    private function user(): User
    {
        return new User(
            new Tenant('alpha', 'Alpha'),
            'tester@alpha.localhost',
            'placeholder',
            ['ROLE_USER'],
            Uuid::v7(),
        );
    }

    /**
     * @param list<string> $codes
     */
    private function resolverWith(User $user, array $codes): PermissionResolverInterface
    {
        $resolver = $this->createMock(PermissionResolverInterface::class);
        $resolver->method('resolve')->with($user)->willReturn(new PermissionSet($codes));

        return $resolver;
    }

    /**
     * @param list<string> $roleIds
     */
    private function stubConnection(
        array $roleIds,
        ?string $perAttribute = null,
        ?string $perGroup = null,
        ?string $roleDefault = null,
    ): Connection {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchAllAssociative')
            ->willReturn(array_map(static fn (string $id): array => ['role_id' => $id], $roleIds));

        $connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql) use ($perAttribute, $perGroup, $roleDefault) {
                if (str_contains($sql, 'FROM role_attribute_permissions')) {
                    return $perAttribute ?? false;
                }
                if (str_contains($sql, 'FROM role_attribute_group_permissions')) {
                    return $perGroup ?? false;
                }
                if (str_contains($sql, 'FROM roles WHERE id')) {
                    return $roleDefault ?? false;
                }

                return false;
            });

        return $connection;
    }
}
