<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\ObjectTypeVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-004 (#667) — unit coverage of ObjectTypeVoter against the
 * PRD §3.2 macierz Modeling codes plus built-in protection.
 */
final class ObjectTypeVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenModelingViewPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $objectType = $this->customObjectType($tenant);
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.view']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $objectType, ['view']),
        );
    }

    #[Test]
    public function grantsAddWhenObjectTypesAddPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.object_types.add']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), ObjectType::class, ['add']),
        );
    }

    #[Test]
    public function grantsEditCustomObjectTypeWhenObjectTypesAddPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $custom = $this->customObjectType($tenant);
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.object_types.add']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $custom, ['edit']),
        );
    }

    #[Test]
    public function grantsDeleteCustomObjectTypeWhenDeleteCustomPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $custom = $this->customObjectType($tenant);
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.delete_custom']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), $custom, ['delete']),
        );
    }

    #[Test]
    public function deniesDeleteBuiltInObjectTypeEvenWithDeleteCustom(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $builtIn = $this->builtInObjectType($tenant);
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.delete_custom']));

        // Built-in protection: the macierz grant alone is not enough — built-in
        // rows back the API sugar paths and must survive every voter call.
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $builtIn, ['delete']),
        );
    }

    #[Test]
    public function deniesDeleteWhenDeleteCustomMissing(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $custom = $this->customObjectType($tenant);
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.view', 'modeling.object_types.add']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $custom, ['delete']),
        );
    }

    #[Test]
    public function deniesAcrossTenantsEvenWithPermission(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $custom = $this->customObjectType($beta);
        $user = $this->user($alpha);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.object_types.add']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), $custom, ['edit']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $custom = $this->customObjectType($tenant);
        $user = $this->user($tenant);

        $voter = new ObjectTypeVoter($this->resolverWith($user, ['modeling.view']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), $custom, ['UNHANDLED']),
        );
    }

    private function customObjectType(Tenant $tenant): ObjectType
    {
        $objectType = new ObjectType('custom-pricelist', ObjectKind::Custom, ['en' => 'Price list']);
        $objectType->assignTenant($tenant);

        return $objectType;
    }

    private function builtInObjectType(Tenant $tenant): ObjectType
    {
        $objectType = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $objectType->markBuiltIn();
        $objectType->assignTenant($tenant);

        return $objectType;
    }

    private function user(Tenant $tenant): User
    {
        return new User($tenant, 'tester@'.$tenant->getCode().'.localhost', 'placeholder');
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

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main');
    }
}
